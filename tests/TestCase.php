<?php

namespace Settings\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Settings\SettingsServiceProvider;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            SettingsServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Use array cache so tests are fully isolated with no disk state
        config()->set('settings.cache.driver', 'array');
    }

    protected function defineDatabaseMigrations(): void
    {
        $migration = include __DIR__.'/../database/migrations/0002_01_01_000001_create_settings_table.php.stub';
        $migration->up();
    }
}
