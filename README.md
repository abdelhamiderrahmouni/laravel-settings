# Laravel Settings

Production-ready, super-simple settings management for Laravel applications.

Store settings in the database with full support for per-user scoping, typed values, caching, and a clean enum-driven API.

## Requirements

- PHP 8.2+
- Laravel 11, 12, or 13

## Installation

```bash
composer require abdelhamiderrahmouni/laravel-settings
```

Publish and run the migration:

```bash
php artisan vendor:publish --tag=settings-migrations
php artisan migrate
```

## Configuration

Publish the config file to customise the cache driver, TTL, cache key prefix, and table name:

```bash
php artisan vendor:publish --tag=settings-config
```

```php
// config/settings.php
return [
    'cache' => [
        'driver' => env('SETTINGS_CACHE_DRIVER', 'file'),
        'ttl'    => env('SETTINGS_CACHE_TTL', 3600),
        'prefix' => env('SETTINGS_CACHE_PREFIX', 'settings'),
    ],
    'table' => env('SETTINGS_TABLE', 'settings'),
];
```

## Defining Settings

Create a settings enum with the `settings:make` command:

```bash
php artisan settings:make GeneralSettings
```

This generates `app/Settings/GeneralSettings.php`. Fill in your cases:

```php
namespace App\Settings;

use Settings\Contracts\SettingDefinition;

enum GeneralSettings: string implements SettingDefinition
{
    case SiteName       = 'site_name';
    case MaintenanceMode = 'maintenance_mode';
    case MaxUploadSize  = 'max_upload_size';

    public function group(): string
    {
        return 'general';
    }

    public function type(): string
    {
        return match ($this) {
            self::SiteName        => 'string',
            self::MaintenanceMode => 'boolean',
            self::MaxUploadSize   => 'integer',
        };
    }

    public function default(): mixed
    {
        return match ($this) {
            self::SiteName        => 'My App',
            self::MaintenanceMode => false,
            self::MaxUploadSize   => 10,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::SiteName        => 'Site Name',
            self::MaintenanceMode => 'Maintenance Mode',
            self::MaxUploadSize   => 'Max Upload Size (MB)',
        };
    }
}
```

Supported types (Eloquent cast strings): `string`, `integer`, `boolean`, `float`, `array`, `json`.

## Usage

### Reading settings

```php
use App\Settings\GeneralSettings;
use Settings\Facades\Settings;

// Returns the stored value, cast to the declared type
Settings::get(GeneralSettings::SiteName);

// Returns 'Fallback' if no record exists in the DB
Settings::get(GeneralSettings::SiteName, 'Fallback');
```

#### Reading all settings in a group

Pass the enum class-string to retrieve every setting in the group as a single array. One DB query is issued and all values are cast and cached individually.

```php
$settings = Settings::get(GeneralSettings::class);
// [
//     'site_name'        => 'My App',
//     'maintenance_mode' => false,
//     'max_upload_size'  => 10,
// ]
```

Any key without a DB record falls back to the enum's declared default.

### Writing settings

```php
Settings::set(GeneralSettings::SiteName, 'My App');
Settings::set(GeneralSettings::MaintenanceMode, true);
Settings::set(GeneralSettings::MaxUploadSize, 25);
```

#### Writing multiple settings at once

Pass the enum class-string and an array of `case value => value` pairs. Unknown keys are silently ignored; each recognised key is validated against the enum.

```php
Settings::set(GeneralSettings::class, [
    'site_name'        => 'My App',
    'maintenance_mode' => true,
    'max_upload_size'  => 25,
]);
```

### Deleting settings

```php
// Removes the DB row. Subsequent get() calls return the enum default.
Settings::forget(GeneralSettings::SiteName);
```

### Per-user settings

Scope any operation to a specific user with `for()`. This includes bulk get and set:

```php
Settings::for($user)->get(GeneralSettings::SiteName);
Settings::for($user)->set(GeneralSettings::SiteName, 'Their App');
Settings::for($user)->forget(GeneralSettings::SiteName);

// Bulk operations are scoped too
Settings::for($user)->get(GeneralSettings::class);
Settings::for($user)->set(GeneralSettings::class, ['site_name' => 'Their App']);
```

Global settings and per-user settings are stored and retrieved independently.

### Dependency injection

The `SettingsManager` is bound as a singleton in the container and can be injected directly:

```php
use Settings\SettingsManager;

class UpdateSiteNameAction
{
    public function __construct(private readonly SettingsManager $settings) {}

    public function handle(string $name): void
    {
        $this->settings->set(GeneralSettings::SiteName, $name);
    }
}
```

## Caching

Settings are cached per-key after the first read. The cache is automatically invalidated when `set()` or `forget()` is called — no manual cache management needed.

Cache keys follow the format: `{prefix}:{group}.{name}` (or `{prefix}:{group}.{name}:{user_id}` for per-user settings).

## Customising the stub

To customise the enum template generated by `settings:make`, publish the stub:

```bash
php artisan vendor:publish --tag=settings-stubs
```

This copies `stubs/settings.stub` into your project. The `settings:make` command will use your published stub automatically.

## Testing

The package test suite uses Pest:

```bash
composer test
```

## License

MIT — see [LICENSE](LICENSE) for details.
