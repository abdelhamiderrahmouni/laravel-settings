<?php

declare(strict_types=1);

namespace Settings\Facades;

use Illuminate\Support\Facades\Facade;
use Settings\SettingsManager;

/**
 * @method static mixed get(\Settings\Contracts\SettingDefinition $setting, mixed $default = null)
 * @method static void set(\Settings\Contracts\SettingDefinition $setting, mixed $value)
 * @method static void forget(\Settings\Contracts\SettingDefinition $setting)
 * @method static \Settings\SettingsManager for(\Illuminate\Contracts\Auth\Authenticatable $user)
 *
 * @see SettingsManager
 */
class Settings extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return SettingsManager::class;
    }
}
