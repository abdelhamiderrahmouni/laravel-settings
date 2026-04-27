<?php

declare(strict_types=1);

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Settings\Contracts\SettingDefinition;
use Settings\Facades\Settings;
use Settings\SettingsManager;

// ---------------------------------------------------------------------------
// Test fixtures
// ---------------------------------------------------------------------------

enum TestSettings: string implements SettingDefinition
{
    case SiteName = 'site_name';
    case DebugMode = 'debug_mode';
    case MaxItems = 'max_items';
    case Ratio = 'ratio';
    case Tags = 'tags';

    public function group(): string
    {
        return 'test';
    }

    public function type(): string
    {
        return match ($this) {
            self::SiteName  => 'string',
            self::DebugMode => 'boolean',
            self::MaxItems  => 'integer',
            self::Ratio     => 'float',
            self::Tags      => 'array',
        };
    }

    public function default(): mixed
    {
        return match ($this) {
            self::SiteName  => 'Default App',
            self::DebugMode => false,
            self::MaxItems  => 10,
            self::Ratio     => 1.5,
            self::Tags      => [],
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::SiteName  => 'Site Name',
            self::DebugMode => 'Debug Mode',
            self::MaxItems  => 'Max Items',
            self::Ratio     => 'Ratio',
            self::Tags      => 'Tags',
        };
    }
}

function makeUser(int $id = 1): Authenticatable
{
    return new class ($id) implements Authenticatable {
        public function __construct(private readonly int $id) {}

        public function getAuthIdentifierName(): string { return 'id'; }
        public function getAuthIdentifier(): int { return $this->id; }
        public function getAuthPasswordName(): string { return 'password'; }
        public function getAuthPassword(): string { return ''; }
        public function getRememberToken(): string { return ''; }
        public function setRememberToken($value): void {}
        public function getRememberTokenName(): string { return ''; }
    };
}

beforeEach(function (): void {
    DB::table('settings')->truncate();
    // Re-bind a fresh SettingsManager so the cache store is clean between tests
    app()->forgetInstance(SettingsManager::class);
    Cache::store('array')->flush();
});

// ---------------------------------------------------------------------------
// get() — defaults
// ---------------------------------------------------------------------------

it('returns the enum default when no db record exists', function (): void {
    expect(Settings::get(TestSettings::SiteName))->toBe('Default App');
});

it('returns the inline default when provided and no db record exists', function (): void {
    expect(Settings::get(TestSettings::SiteName, 'Custom Default'))->toBe('Custom Default');
});

it('inline default takes precedence over enum default when no record exists', function (): void {
    expect(Settings::get(TestSettings::MaxItems, 99))->toBe(99);
});

// ---------------------------------------------------------------------------
// set() and get() round-trip
// ---------------------------------------------------------------------------

it('persists and retrieves a string setting', function (): void {
    Settings::set(TestSettings::SiteName, 'My App');

    expect(Settings::get(TestSettings::SiteName))->toBe('My App');
});

it('persists and retrieves a boolean setting', function (): void {
    Settings::set(TestSettings::DebugMode, true);

    expect(Settings::get(TestSettings::DebugMode))->toBeTrue();
});

it('persists and retrieves an integer setting', function (): void {
    Settings::set(TestSettings::MaxItems, 42);

    expect(Settings::get(TestSettings::MaxItems))->toBe(42);
});

it('persists and retrieves a float setting', function (): void {
    Settings::set(TestSettings::Ratio, 3.14);

    expect(Settings::get(TestSettings::Ratio))->toBe(3.14);
});

it('persists and retrieves an array setting', function (): void {
    Settings::set(TestSettings::Tags, ['php', 'laravel']);

    expect(Settings::get(TestSettings::Tags))->toBe(['php', 'laravel']);
});

it('overwrites an existing setting on subsequent set() calls', function (): void {
    Settings::set(TestSettings::SiteName, 'First');
    Settings::set(TestSettings::SiteName, 'Second');

    expect(Settings::get(TestSettings::SiteName))->toBe('Second');
});

// ---------------------------------------------------------------------------
// forget()
// ---------------------------------------------------------------------------

it('returns the default after forget()', function (): void {
    Settings::set(TestSettings::SiteName, 'My App');
    Settings::forget(TestSettings::SiteName);

    expect(Settings::get(TestSettings::SiteName))->toBe('Default App');
});

it('forget() on non-existent key does not throw', function (): void {
    expect(fn () => Settings::forget(TestSettings::SiteName))->not->toThrow(Throwable::class);
});

// ---------------------------------------------------------------------------
// per-user scoping
// ---------------------------------------------------------------------------

it('stores global and per-user settings independently', function (): void {
    $user = makeUser(1);

    Settings::set(TestSettings::SiteName, 'Global App');
    Settings::for($user)->set(TestSettings::SiteName, 'User App');

    expect(Settings::get(TestSettings::SiteName))->toBe('Global App')
        ->and(Settings::for($user)->get(TestSettings::SiteName))->toBe('User App');
});

it('different users have independent settings', function (): void {
    $userA = makeUser(1);
    $userB = makeUser(2);

    Settings::for($userA)->set(TestSettings::SiteName, 'User A App');
    Settings::for($userB)->set(TestSettings::SiteName, 'User B App');

    expect(Settings::for($userA)->get(TestSettings::SiteName))->toBe('User A App')
        ->and(Settings::for($userB)->get(TestSettings::SiteName))->toBe('User B App');
});

it('forget() for a user does not affect global setting', function (): void {
    $user = makeUser(1);

    Settings::set(TestSettings::SiteName, 'Global');
    Settings::for($user)->set(TestSettings::SiteName, 'User');
    Settings::for($user)->forget(TestSettings::SiteName);

    expect(Settings::get(TestSettings::SiteName))->toBe('Global')
        ->and(Settings::for($user)->get(TestSettings::SiteName))->toBe('Default App');
});

// ---------------------------------------------------------------------------
// Caching
// ---------------------------------------------------------------------------

it('returns cached value without hitting db a second time', function (): void {
    Settings::set(TestSettings::SiteName, 'Cached App');

    // First get — populates cache
    Settings::get(TestSettings::SiteName);

    // Manually corrupt DB row to prove next read comes from cache
    DB::table('settings')
        ->where('group', 'test')
        ->where('name', 'site_name')
        ->whereNull('user_id')
        ->update(['payload' => json_encode(['value' => 'DB Changed'])]);

    expect(Settings::get(TestSettings::SiteName))->toBe('Cached App');
});

it('invalidates cache on set()', function (): void {
    Settings::set(TestSettings::SiteName, 'First');
    Settings::get(TestSettings::SiteName); // populate cache

    Settings::set(TestSettings::SiteName, 'Updated');

    expect(Settings::get(TestSettings::SiteName))->toBe('Updated');
});

it('invalidates cache on forget()', function (): void {
    Settings::set(TestSettings::SiteName, 'Cached');
    Settings::get(TestSettings::SiteName); // populate cache

    Settings::forget(TestSettings::SiteName);

    expect(Settings::get(TestSettings::SiteName))->toBe('Default App');
});

// ---------------------------------------------------------------------------
// DI — injectable SettingsManager
// ---------------------------------------------------------------------------

it('can be resolved from the container', function (): void {
    expect(app(SettingsManager::class))->toBeInstanceOf(SettingsManager::class);
});

it('injectable instance behaves the same as the facade', function (): void {
    /** @var SettingsManager $manager */
    $manager = app(SettingsManager::class);
    $manager->set(TestSettings::SiteName, 'Injected');

    expect($manager->get(TestSettings::SiteName))->toBe('Injected');
});
