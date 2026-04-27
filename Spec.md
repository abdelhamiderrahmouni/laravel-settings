# Laravel Settings — Design Spec

## API Shape

```php
// Global settings
Settings::get(GeneralSettings::SiteName);
Settings::get(GeneralSettings::SiteName, 'fallback');
Settings::set(GeneralSettings::SiteName, 'My App');
Settings::forget(GeneralSettings::SiteName);

// Per-user settings
Settings::for($user)->get(GeneralSettings::SiteName);
Settings::for($user)->set(GeneralSettings::SiteName, 'My App');
Settings::for($user)->forget(GeneralSettings::SiteName);
```

## Enum Contract (`SettingDefinition` Interface)

Every settings enum must implement `SettingDefinition`:

```php
interface SettingDefinition
{
    public function group(): string;
    public function type(): string;    // Eloquent cast strings: 'string', 'integer', 'boolean', 'array', 'json'
    public function default(): mixed;
    public function label(): string;
}
```

Example enum:

```php
enum GeneralSettings: string implements SettingDefinition
{
    case SiteName = 'site_name';
    case MaintenanceMode = 'maintenance_mode';

    public function group(): string
    {
        return 'general';
    }

    public function type(): string
    {
        return match($this) {
            self::SiteName => 'string',
            self::MaintenanceMode => 'boolean',
        };
    }

    public function default(): mixed
    {
        return match($this) {
            self::SiteName => 'My App',
            self::MaintenanceMode => false,
        };
    }

    public function label(): string
    {
        return match($this) {
            self::SiteName => 'Site Name',
            self::MaintenanceMode => 'Maintenance Mode',
        };
    }
}
```

## Storage

- Table: `settings` (configurable via config)
- Columns: `id`, `user_id` (nullable FK), `group`, `name`, `payload` (json), `created_at`, `updated_at`
- **Unique index on `(group, name, user_id)`**
- `set()` uses `upsert()` for atomic write

## Caching

- Strategy: per-key caching
- Cache key format: `{prefix}:{group}.{name}[:{user_id}]`
- Configurable: driver, TTL, prefix
- Auto-invalidated on `set()` and `forget()`

## Config File (`config/settings.php`)

```php
return [
    'cache' => [
        'driver' => env('SETTINGS_CACHE_DRIVER', 'file'),
        'ttl'    => env('SETTINGS_CACHE_TTL', 3600),
        'prefix' => env('SETTINGS_CACHE_PREFIX', 'settings'),
    ],
    'table' => env('SETTINGS_TABLE', 'settings'),
];
```

## Class Structure

| File | Purpose |
|---|---|
| `src/Contracts/SettingDefinition.php` | Interface all settings enums must implement |
| `src/SettingsManager.php` | Core class — get, set, forget, for() |
| `src/Facades/Settings.php` | Laravel facade pointing to SettingsManager |
| `src/Console/Commands/MakeSettingsCommand.php` | `php artisan settings:make {Name}` |
| `config/settings.php` | Published config file |
| `src/SettingsServiceProvider.php` | Register bindings, config, commands |
| `database/migrations/...stub` | Updated with unique index |

## Artisan Command

```bash
php artisan settings:make GeneralSettings
# Generates: app/Settings/GeneralSettings.php
```

Scaffolds a stub enum implementing `SettingDefinition` with example cases.

## DI / Container

- `SettingsManager` bound as a singleton in the container
- Resolvable via `app(SettingsManager::class)` or the `Settings` facade
- `for($user)` returns a new scoped instance (does not mutate the singleton)
