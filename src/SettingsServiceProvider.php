<?php

namespace Settings;

use Illuminate\Support\ServiceProvider;

class SettingsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../database/migrations/0002_01_01_000001_create_settings_table.php' => database_path('migrations/0002_01_01_000001_create_settings_table.php.stub'),
        ], 'settings-migrations');

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    public function register(): void
    {
        //
    }
}
