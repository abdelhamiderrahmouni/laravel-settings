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
     * Retrieve a setting value or all settings for an enum class.
     *
     * - Pass an enum case to get a single typed value.
     * - Pass an enum class-string to get all settings in that group as an
     *   array keyed by the enum case value.
     *
     * @template T of \BackedEnum&SettingDefinition
     * @param  (T)|class-string<T>  $setting
     * @return ($setting is class-string ? array<string, mixed> : mixed)
     */
    public function get(SettingDefinition|\BackedEnum|string $setting, mixed $default = null): mixed
    {
        if (is_string($setting)) {
            return $this->getGroup($setting);
        }

        /** @var SettingDefinition&\BackedEnum $setting */
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
     * Persist a setting value or a batch of values for an enum class.
     *
     * - Pass an enum case + scalar value to set a single setting.
     * - Pass an enum class-string + array to set multiple settings at once.
     *   Each key in $value must match an enum case value; unknown keys are ignored.
     *
     * @template T of \BackedEnum&SettingDefinition
     * @param  (T)|class-string<T>  $setting
     * @param  mixed|array<string, mixed>  $value
     */
    public function set(SettingDefinition|\BackedEnum|string $setting, mixed $value): void
    {
        if (is_string($setting)) {
            $this->setGroup($setting, (array) $value);

            return;
        }

        /** @var SettingDefinition&\BackedEnum $setting */
        $this->persistSingle($setting, $value);
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
    // Bulk operations
    // -------------------------------------------------------------------------

    /**
     * Fetch all settings for an enum class in a single query.
     * Returns an array keyed by enum case value, with each value cast to its declared type.
     *
     * @template T of \BackedEnum&SettingDefinition
     * @param  class-string<T>  $enumClass
     * @return array<string, mixed>
     */
    private function getGroup(string $enumClass): array
    {
        $this->assertValidSettingEnum($enumClass);

        /** @var array<T> $cases */
        $cases = $enumClass::cases();

        $group = $cases[0]->group();

        $query = $this->db->table($this->table)->where('group', $group);

        if ($this->userId() === null) {
            $query->whereNull('user_id');
        } else {
            $query->where('user_id', $this->userId());
        }

        $rows = $query->get()->keyBy('name');

        $result = [];

        foreach ($cases as $case) {
            $cacheKey = $this->cacheKey($case);

            $result[$case->value] = $this->cache->remember(
                $cacheKey,
                $this->cacheTtl,
                function () use ($case, $rows): mixed {
                    $row = $rows->get($case->value);

                    if ($row === null) {
                        return null;
                    }

                    $decoded = json_decode((string) $row->payload, associative: true);

                    return $decoded['value'] ?? null;
                },
            );

            if ($result[$case->value] === null) {
                $result[$case->value] = $case->default();
            } else {
                $result[$case->value] = $this->cast($result[$case->value], $case->type());
            }
        }

        return $result;
    }

    /**
     * Persist multiple settings for an enum class.
     * Keys in $data must match enum case values; unrecognised keys are silently ignored.
     *
     * @template T of \BackedEnum&SettingDefinition
     * @param  class-string<T>  $enumClass
     * @param  array<string, mixed>  $data
     */
    private function setGroup(string $enumClass, array $data): void
    {
        $this->assertValidSettingEnum($enumClass);

        /** @var array<T> $cases */
        $cases = $enumClass::cases();

        $casesByValue = array_column($cases, null, 'value');

        foreach ($data as $key => $value) {
            if (! isset($casesByValue[$key])) {
                continue;
            }

            $this->persistSingle($casesByValue[$key], $value);
        }
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    /**
     * @param  SettingDefinition&\BackedEnum  $setting
     */
    private function persistSingle(SettingDefinition $setting, mixed $value): void
    {
        /** @var SettingDefinition&\BackedEnum $setting */
        $payload = $this->prepareForStorage($value, $setting->type());

        $match = [
            'group'   => $setting->group(),
            'name'    => $setting->value,
            'user_id' => $this->userId(),
        ];

        $this->db->table($this->table)->updateOrInsert(
            $match,
            [
                'payload'    => json_encode($payload),
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );

        $this->cache->forget($this->cacheKey($setting));
    }

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
            'integer', 'int'             => (int) $value,
            'boolean', 'bool'            => (bool) $value,
            'float', 'double', 'real'    => (float) $value,
            'array', 'json'              => is_array($value) ? $value : json_decode((string) $value, associative: true),
            default                      => (string) $value,
        };
    }

    private function prepareForStorage(mixed $value, string $type): array
    {
        return ['value' => $this->cast($value, $type)];
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

    /**
     * Assert that a class-string is a backed enum implementing SettingDefinition.
     *
     * @param  class-string  $enumClass
     */
    private function assertValidSettingEnum(string $enumClass): void
    {
        if (! enum_exists($enumClass)) {
            throw new \InvalidArgumentException("[{$enumClass}] is not an enum.");
        }

        if (! is_a($enumClass, SettingDefinition::class, allow_string: true)) {
            throw new \InvalidArgumentException("[{$enumClass}] does not implement SettingDefinition.");
        }
    }
}
