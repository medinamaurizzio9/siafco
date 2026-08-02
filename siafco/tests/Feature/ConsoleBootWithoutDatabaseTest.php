<?php

namespace Tests\Feature;

use Symfony\Component\Process\Process;
use Tests\TestCase;

class ConsoleBootWithoutDatabaseTest extends TestCase
{
    public function test_artisan_can_boot_for_discovery_when_configured_database_does_not_exist(): void
    {
        $process = new Process([PHP_BINARY, 'artisan', 'list', '--no-ansi'], base_path(), [
            'APP_ENV' => 'local',
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => '127.0.0.1',
            'DB_PORT' => '3306',
            'DB_DATABASE' => 'siafco_missing_for_console_boot',
            'DB_USERNAME' => 'root',
            'DB_PASSWORD' => '',
        ]);

        $process->setTimeout(30);
        $process->run();

        $this->assertSame(
            0,
            $process->getExitCode(),
            $process->getErrorOutput().$process->getOutput()
        );
        $this->assertStringContainsString('package:discover', $process->getOutput());
    }
}
