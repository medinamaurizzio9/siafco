<?php

use App\Models\SimpleInvestmentContract;
use App\Models\SimpleInvestmentSequence;
use App\Models\SimpleInvestmentSetting;
use App\Models\User;
use App\Services\SimpleInvestmentContractService;
use App\Services\SimpleInvestmentInvestorService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

// Invoked only by VerifyContractConcurrency.php with a schema-restricted temporary SQL account.
require dirname(__DIR__, 2).'/vendor/autoload.php';
$schema = getenv('CONTRACT_RACE_DATABASE');
if (! preg_match('/^siafco_contract_race_[a-f0-9]{12}$/', $schema ?: '')
    || getenv('DB_DATABASE') !== $schema || getenv('DB_HOST') !== '127.0.0.1'
    || getenv('DB_PORT') !== '3306' || getenv('APP_ENV') !== 'testing') {
    throw new RuntimeException('Isolated MySQL environment required.');
}
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (config('database.default') !== 'mysql' || config('database.connections.mysql.database') !== $schema) {
    throw new RuntimeException('Unexpected database connection.');
}
$service = app(SimpleInvestmentContractService::class);
if (($argv[1] ?? '') === 'setup') {
    SimpleInvestmentSetting::create(['product_name' => 'Synthetic unit', 'unit_price' => '14000.00',
        'currency' => 'BOB', 'minimum_units' => 1, 'maximum_units' => 20, 'first_schedule_days' => 40, 'schedule_interval_days' => 30, 'is_active' => true]);
    foreach (['investor', 'contract', 'unit'] as $key) {
        SimpleInvestmentSequence::create(['key' => $key, 'last_value' => 0]);
    }
    $actor = User::create(['name' => 'Synthetic race actor', 'email' => 'race@example.test', 'role' => 'supervisor_inversiones',
        'user_type' => 'internal', 'is_active' => true, 'password' => Hash::make(bin2hex(random_bytes(16)))]);
    Auth::login($actor);
    $investor = app(SimpleInvestmentInvestorService::class)->create(['full_name' => 'Synthetic race investor', 'ci' => 'RACE-SYNTHETIC',
        'registered_on' => '2026-10-30', 'is_active' => true], $actor);
    $contracts = [];
    foreach (range(1, 3) as $i) {
        $contract = $service->create(['investor_id' => $investor->id, 'quantity' => 2, 'registered_on' => '2026-10-30'], $actor);
        if ($i > 1) {
            $service->markPending($contract);
        }
        $contracts[] = $contract->id;
    }
    echo json_encode(['actor' => $actor->id, 'contracts' => $contracts]),PHP_EOL;
    exit;
}
$contract = SimpleInvestmentContract::findOrFail((int) $argv[2]);
$actor = User::findOrFail((int) $argv[3]);
Auth::login($actor);
$connectionId = DB::selectOne('SELECT CONNECTION_ID() AS id')->id;
// Both workers load the instance before the parent releases the current row lock.
echo json_encode(['ready' => true, 'connection_id' => $connectionId]),PHP_EOL;
flush();
fgets(STDIN);
try {
    $result = match ($argv[1]) {
        'pending' => $service->markPending($contract),
        'sign' => $service->sign($contract, $actor, '2026-10-30'),
        'void' => $service->void($contract, $actor, 'Synthetic concurrency cancellation'),
        default => throw new RuntimeException('Unknown operation.'),
    };
    echo json_encode(['outcome' => 'accepted', 'status' => $result->status]),PHP_EOL;
} catch (ValidationException $e) {
    echo json_encode(['outcome' => 'rejected', 'errors' => $e->errors()]),PHP_EOL;
}
