<?php

declare(strict_types=1);

namespace Settings;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\ServiceProvider;
use Settings\Console\Commands\MakeSettingsCommand;

class SettingsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../database/migrations/0002_01_01_000001_create_settings_table.php.stub'
                => database_path('migrations/0002_01_01_000001_create_settings_table.php'),
        ], 'settings-migrations');

        $this->publishes([
            __DIR__.'/../config/settings.php' => config_path('settings.php'),
        ], 'settings-config');

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->publishes([
            __DIR__.'/../stubs/settings.stub' => base_path('stubs/settings.stub'),
        ], 'settings-stubs');

        if ($this->app->runningInConsole()) {
            $this->commands([
                MakeSettingsCommand::class,
            ]);
        }
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/settings.php', 'settings');

        $this->app->singleton(SettingsManager::class, function ($app) {
            /** @var array{cache: array{driver: string, ttl: int, prefix: string}, table: string} $config */
            $config = $app['config']['settings'];

            $cacheStore = Cache::store($config['cache']['driver']);

            return new SettingsManager(
                db: $app['db'],
                cache: $cacheStore,
                table: $config['table'],
                cachePrefix: $config['cache']['prefix'],
                cacheTtl: $config['cache']['ttl'],
            );
        });
    }
}
