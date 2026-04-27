<?php

declare(strict_types=1);

namespace Settings;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Database\DatabaseManager;
use Settings\Contracts\SettingDefinition;

class SettingsManager
{
    private ?Authenticatable $user = null;

    public function __construct(
        private readonly DatabaseManager $db,
        private readonly CacheRepository $cache,
        private readonly string $table,
        private readonly string $cachePrefix,
        private readonly int $cacheTtl,
    ) {}

    /**
     * Scope subsequent operations to a specific user.
     */
    public function for(Authenticatable $user): static
    {
        $clone = clone $this;
        $clone->user = $user;

        return $clone;
    }

    /**
     * Retrieve a setting value, cast to the type declared on the enum.
     */
    public function get(SettingDefinition&\BackedEnum $setting, mixed $default = null): mixed
    {
        $cacheKey = $this->cacheKey($setting);

        $raw = $this->cache->remember(
            $cacheKey,
            $this->cacheTtl,
            fn (): mixed => $this->fetchRaw($setting),
        );

        if ($raw === null) {
            return $default ?? $setting->default();
        }

        return $this->cast($raw, $setting->type());
    }

    /**
     * Persist a setting value, casting it before storage.
     */
    public function set(SettingDefinition&\BackedEnum $setting, mixed $value): void
    {
        $payload = $this->prepareForStorage($value, $setting->type());

        $match = [
            'group' => $setting->group(),
            'name' => $setting->value,
            'user_id' => $this->userId(),
        ];

        $this->db->table($this->table)->updateOrInsert(
            $match,
            [
                'payload' => json_encode($payload),
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );

        $this->cache->forget($this->cacheKey($setting));
    }

    /**
     * Delete a setting row. get() will return the default afterwards.
     */
    public function forget(SettingDefinition&\BackedEnum $setting): void
    {
        $query = $this->db->table($this->table)
            ->where('group', $setting->group())
            ->where('name', $setting->value);

        if ($this->userId() === null) {
            $query->whereNull('user_id');
        } else {
            $query->where('user_id', $this->userId());
        }

        $query->delete();

        $this->cache->forget($this->cacheKey($setting));
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    private function fetchRaw(SettingDefinition&\BackedEnum $setting): mixed
    {
        $query = $this->db->table($this->table)
            ->where('group', $setting->group())
            ->where('name', $setting->value);

        if ($this->userId() === null) {
            $query->whereNull('user_id');
        } else {
            $query->where('user_id', $this->userId());
        }

        $row = $query->first();

        if ($row === null) {
            return null;
        }

        $decoded = json_decode((string) $row->payload, associative: true);

        return $decoded['value'] ?? null;
    }

    private function cast(mixed $value, string $type): mixed
    {
        return match ($type) {
            'integer', 'int' => (int) $value,
            'boolean', 'bool' => (bool) $value,
            'float', 'double', 'real' => (float) $value,
            'array', 'json' => is_array($value) ? $value : json_decode((string) $value, associative: true),
            default => (string) $value,
        };
    }

    private function prepareForStorage(mixed $value, string $type): array
    {
        $cast = $this->cast($value, $type);

        return ['value' => $cast];
    }

    private function cacheKey(SettingDefinition&\BackedEnum $setting): string
    {
        $key = sprintf('%s:%s.%s', $this->cachePrefix, $setting->group(), $setting->value);

        if ($this->userId() !== null) {
            $key .= ':'.$this->userId();
        }

        return $key;
    }

    private function userId(): int|string|null
    {
        return $this->user?->getAuthIdentifier();
    }
}
