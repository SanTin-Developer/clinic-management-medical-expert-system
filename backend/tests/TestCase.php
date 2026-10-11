<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Create the application for the test.
     *
     * The test environment is hard-pinned to an in-memory SQLite database.
     * Docker Compose injects DB_CONNECTION/DB_DATABASE into $_ENV and $_SERVER,
     * and PHPUnit's "force" only overrides putenv(). Laravel's Env repository
     * reads $_ENV first, so without this guard the test suite would resolve the
     * live PostgreSQL database and RefreshDatabase would wipe it.
     */
    public function createApplication()
    {
        foreach ([
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => ':memory:',
            'DB_URL' => '',
            'CACHE_STORE' => 'array',
            'SESSION_DRIVER' => 'array',
            'QUEUE_CONNECTION' => 'sync',
            'MAIL_MAILER' => 'array',
        ] as $key => $value) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }

        return parent::createApplication();
    }
}
