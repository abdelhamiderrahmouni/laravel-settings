<?php

declare(strict_types=1);

namespace Settings\Facades;

use Illuminate\Support\Facades\Facade;
use Settings\SettingsManager;

/**
 * @method static mixed                get(\Settings\Contracts\SettingDefinition&\BackedEnum $setting, mixed $default = null)
 * @method static array<string, mixed> get(class-string<\Settings\Contracts\SettingDefinition&\BackedEnum> $enumClass)
 * @method static void                 set(\Settings\Contracts\SettingDefinition&\BackedEnum $setting, mixed $value)
 * @method static void                 set(class-string<\Settings\Contracts\SettingDefinition&\BackedEnum> $enumClass, array<string, mixed> $data)
 * @method static void                 forget(\Settings\Contracts\SettingDefinition&\BackedEnum $setting)
 * @method static \Settings\SettingsManager for(\Illuminate\Contracts\Auth\Authenticatable $user)
 *
 * @see \Settings\SettingsManager
 */
class Settings extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return SettingsManager::class;
    }
}
