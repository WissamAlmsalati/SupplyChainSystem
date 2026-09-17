<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    // Guarded routes are fail-closed (see CheckPermission), so every test run
    // starts with the real roles and permission codes, seeded once per process.
    protected $seed = true;

    protected $seeder = \Database\Seeders\AccessControlSeeder::class;

    /**
     * Create the application with test-only config. phpunit.xml forces values
     * with <env force="true">, which writes $_ENV and putenv() only, while
     * Laravel's env() reads $_SERVER first and the Docker containers export
     * DB_*, CACHE_STORE, QUEUE_CONNECTION… there. Mirror the forced values into
     * $_SERVER so tests never touch the dev MySQL or the shared Redis.
     */
    public function createApplication(): Application
    {
        foreach ($_ENV as $key => $value) {
            if (is_string($value) && ($_SERVER[$key] ?? null) !== $value) {
                $_SERVER[$key] = $value;
                putenv("{$key}={$value}");
            }
        }

        $defaults = [
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => 'db',
            'DB_PORT' => '3306',
            'DB_DATABASE' => 'cafe_supply_chain_test',
            'DB_USERNAME' => 'cafe_user',
            'DB_PASSWORD' => 'cafe_pass',
            'DB_URL' => '',
        ];

        foreach ($defaults as $key => $value) {
            $envValue = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
            $envValue = $envValue !== false && $envValue !== '' ? $envValue : $value;

            putenv("{$key}={$envValue}");
            $_ENV[$key] = $envValue;
            $_SERVER[$key] = $envValue;
        }

        $app = require __DIR__.'/../bootstrap/app.php';
        $app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

        return $app;
    }
}
