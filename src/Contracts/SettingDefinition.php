<?php

declare(strict_types=1);

namespace Settings\Contracts;

interface SettingDefinition
{
    /**
     * The group this setting belongs to.
     * Maps to the 'group' column in the settings table.
     */
    public function group(): string;

    /**
     * The Eloquent cast type for this setting's value.
     * Supported: 'string', 'integer', 'boolean', 'float', 'array', 'json'
     */
    public function type(): string;

    /**
     * The default value returned when this setting has no DB record.
     */
    public function default(): mixed;

    /**
     * A human-readable label for this setting (e.g. for UI display).
     */
    public function label(): string;
}
