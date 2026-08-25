<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Create the application with test-only database config so the dev
     * database (and its seeded admin user) survives test runs.
     */
    public function createApplication(): Application
    {
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
