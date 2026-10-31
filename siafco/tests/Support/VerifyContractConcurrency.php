<?php

use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Foundation\Bootstrap\LoadEnvironmentVariables;

// Opt-in local runner: no Artisan migrations and no writes to siafco_local.
require dirname(__DIR__, 2).'/vendor/autoload.php';
chdir(dirname(__DIR__, 2));
$app = require 'bootstrap/app.php';
(new LoadEnvironmentVariables)->bootstrap($app);
(new LoadConfiguration)->bootstrap($app);
$db = $app['config']->get('database.connections.mysql');
if ($app['config']->get('app.env') !== 'local' || $db['host'] !== '127.0.0.1' || (string) $db['port'] !== '3306'
    || $db['database'] !== 'siafco_local' || ! empty($db['url']) || ! empty($db['unix_socket'])
    || trim(shell_exec('git branch --show-current')) !== 'feature/inversiones-simple') {
    throw new RuntimeException('Local-only preflight failed.');
}
foreach (['AWS_ACCESS_KEY_ID', 'AWS_SECRET_ACCESS_KEY', 'AWS_SESSION_TOKEN', 'AWS_PROFILE', 'AWS_ENDPOINT'] as $key) {
    if (env($key)) {
        throw new RuntimeException('AWS configuration present.');
    }
}
$pdo = new PDO('mysql:host=127.0.0.1;port=3306', $db['username'], $db['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
if ($pdo->query('SELECT VERSION()')->fetchColumn() !== '8.4.3') {
    throw new RuntimeException('MySQL 8.4.3 required.');
}
$token = bin2hex(random_bytes(6));
$schema = 'siafco_contract_race_'.$token;
$user = 'contract_race_'.$token;
$password = bin2hex(random_bytes(24));
$tables = $pdo->query('SHOW FULL TABLES FROM siafco_local WHERE Table_type="BASE TABLE"')->fetchAll(PDO::FETCH_COLUMN);
$before = [];
foreach ($tables as $table) {
    $before[$table] = $pdo->query('CHECKSUM TABLE siafco_local.`'.$table.'` EXTENDED')->fetch(PDO::FETCH_NUM)[1];
}
$env = array_replace(getenv(), ['APP_ENV' => 'testing', 'APP_KEY' => 'base64:'.base64_encode(random_bytes(32)),
    'APP_CONFIG_CACHE' => 'tests/.unused-contract-race-config.php', 'APP_ROUTES_CACHE' => 'tests/.unused-contract-race-routes.php',
    'DB_CONNECTION' => 'mysql', 'DB_HOST' => '127.0.0.1', 'DB_PORT' => '3306', 'DB_DATABASE' => $schema, 'DB_USERNAME' => $user, 'DB_PASSWORD' => $password,
    'DB_URL' => '', 'DB_SOCKET' => '', 'CONTRACT_RACE_DATABASE' => $schema, 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'MAIL_MAILER' => 'array',
    'QUEUE_CONNECTION' => 'sync', 'AWS_EC2_METADATA_DISABLED' => 'true']);
$worker = __DIR__.'/ContractRaceWorker.php';
$start = function (array $args) use ($env, $worker): array {
    $p = proc_open([PHP_BINARY, $worker, ...array_map('strval', $args)], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, getcwd(), $env, ['bypass_shell' => true]);
    if (! is_resource($p)) {
        throw new RuntimeException('Worker failed to start.');
    }

    return [$p, $pipes];
};
$finish = function (array $process): array {
    [$p,$pipes] = $process;
    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    if (proc_close($p) !== 0 || trim($error) !== '') {
        throw new RuntimeException('Worker failed: '.$error);
    }

    return json_decode(trim($output), true, flags: JSON_THROW_ON_ERROR);
};
$report = ['mysql' => '8.4.3', 'schema' => $schema, 'migrations_executed' => false];
$created = false;
$account = false;
try {
    $pdo->exec('CREATE DATABASE `'.$schema.'`');
    $created = true;
    $pdo->exec('USE `'.$schema.'`');
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    foreach ($tables as $table) {
        $ddl = $pdo->query('SHOW CREATE TABLE siafco_local.`'.$table.'`')->fetch(PDO::FETCH_NUM)[1];
        $pdo->exec($ddl); // Structure only. No personal or business rows are copied.
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    $pdo->exec('CREATE USER '.$pdo->quote($user)."@'127.0.0.1' IDENTIFIED BY ".$pdo->quote($password));
    $account = true;
    $pdo->exec('GRANT SELECT,INSERT,UPDATE,DELETE ON `'.$schema.'`.* TO '.$pdo->quote($user)."@'127.0.0.1'");
    $setup = $finish($start(['setup']));
    $race = function (int $contract, array $actions) use ($pdo, $schema, $start, $finish, $setup): array {
        $jobs = [];
        $ids = [];
        $pdo->beginTransaction();
        $pdo->query('SELECT id FROM `'.$schema.'`.simple_investment_contracts WHERE id='.$contract.' FOR UPDATE')->fetch();
        try {
            foreach ($actions as $action) {
                $job = $start([$action, $contract, $setup['actor']]);
                $ready = json_decode(trim(fgets($job[1][1])), true, flags: JSON_THROW_ON_ERROR);
                $ids[] = (int) $ready['connection_id'];
                $jobs[] = $job;
            }
            foreach ($jobs as $job) {
                fwrite($job[1][0], "GO\n");
                fflush($job[1][0]);
            }
            $deadline = microtime(true) + 15;
            $waiting = 0;
            do {
                $waiting = (int) $pdo->query('SELECT COUNT(DISTINCT t.PROCESSLIST_ID) FROM performance_schema.data_lock_waits w JOIN performance_schema.threads t ON t.THREAD_ID=w.REQUESTING_THREAD_ID WHERE t.PROCESSLIST_ID IN ('.implode(',', $ids).')')->fetchColumn();
                if ($waiting === count($jobs)) {
                    break;
                }
                usleep(20000);
            } while (microtime(true) < $deadline);
            if ($waiting !== count($jobs)) {
                throw new RuntimeException('Could not prove simultaneous row lock waits.');
            }
            $pdo->commit();

            return ['simultaneous_waiters' => $waiting, 'results' => array_map($finish, $jobs)];
        } finally {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            foreach ($jobs as [$p,$pipes]) {
                if (is_resource($p)) {
                    proc_terminate($p);
                }
            }
        }
    };
    [$draft,$pending,$voiding] = $setup['contracts'];
    $report['pending_vs_pending'] = $race($draft, ['pending', 'pending']);
    $report['sign_vs_stale_pending'] = $race($pending, ['sign', 'pending']);
    $report['sign_vs_void'] = $race($voiding, ['sign', 'void']);
    foreach ([$draft, $pending, $voiding] as $id) {
        $report['contracts'][$id] = $pdo->query('SELECT status,signed_on,first_schedule_days_snapshot,schedule_interval_days_snapshot,(SELECT COUNT(*) FROM simple_investment_units u WHERE u.contract_id=c.id) AS units FROM simple_investment_contracts c WHERE id='.$id)->fetch(PDO::FETCH_ASSOC);
    }
    $assert = function (bool $condition): void {
        if (! $condition) {
            throw new RuntimeException('Concurrency postcondition failed.');
        }
    };
    $assert(array_count_values(array_column($report['pending_vs_pending']['results'], 'outcome')) === ['accepted' => 1, 'rejected' => 1]
        || array_count_values(array_column($report['pending_vs_pending']['results'], 'outcome')) === ['rejected' => 1, 'accepted' => 1]);
    $assert($report['contracts'][$draft]['status'] === 'pending_signature');
    $assert($report['contracts'][$pending]['status'] === 'signed' && (int) $report['contracts'][$pending]['units'] === 2);
    $assert($report['contracts'][$pending]['signed_on'] === '2026-10-30');
    $assert((int) $report['contracts'][$pending]['first_schedule_days_snapshot'] === 40 && (int) $report['contracts'][$pending]['schedule_interval_days_snapshot'] === 30);
    $assert($report['contracts'][$voiding]['status'] === 'void');
    $report['audit_counts'] = $pdo->query('SELECT action,COUNT(*) AS total FROM audit_logs GROUP BY action')->fetchAll(PDO::FETCH_ASSOC);
    $report['duplicate_units'] = (int) $pdo->query('SELECT COUNT(*) FROM (SELECT code FROM simple_investment_units GROUP BY code HAVING COUNT(*)>1) d')->fetchColumn();
    $assert($report['duplicate_units'] === 0);
    foreach ($before as $table => $checksum) {
        $assert($pdo->query('CHECKSUM TABLE siafco_local.`'.$table.'` EXTENDED')->fetch(PDO::FETCH_NUM)[1] === $checksum);
    }
    $report['principal_tables_unchanged'] = count($before);
    $report['status'] = 'passed';
} catch (Throwable $e) {
    $report['status'] = 'failed';
    $report['error'] = $e->getMessage();
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ($account) {
        $pdo->exec('DROP USER '.$pdo->quote($user)."@'127.0.0.1'");
        $report['temporary_account_removed'] = true;
    }
    if ($created && preg_match('/^siafco_contract_race_[a-f0-9]{12}$/', $schema)) {
        $pdo->exec('DROP DATABASE `'.$schema.'`');
        $report['temporary_schema_removed'] = true;
    }
    $file = dirname(getcwd()).'/local-validation-reports/contract_concurrency_'.$token.'.json';
    file_put_contents($file, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo json_encode(['report' => $file, ...$report], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),PHP_EOL;
}
exit(($report['status'] ?? '') === 'passed' ? 0 : 1);
