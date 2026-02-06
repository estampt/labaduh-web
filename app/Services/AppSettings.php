<?php

namespace App\Services;

use App\Models\AppSetting;

class AppSettings
{
    public function get(string $key, mixed $default = null): mixed
    {
        $row = AppSetting::query()->where('key', $key)->first();
        if (!$row) return $default;

        return $this->cast($row->value, $row->type, $default);
    }

    public function set(string $key, mixed $value, string $type = 'string', ?string $group = null, ?int $updatedBy = null): AppSetting
    {
        return AppSetting::updateOrCreate(
            ['key' => $key],
            [
                'value' => $this->stringify($value, $type),
                'type' => $type,
                'group' => $group,
                'updated_by' => $updatedBy,
            ]
        );
    }

    public function getGroup(string $group): array
    {
        $rows = AppSetting::query()->where('group', $group)->get();
        $out = [];
        foreach ($rows as $row) {
            $out[$this->leafKey($row->key, $group)] = $this->cast($row->value, $row->type, null);
        }
        return $out;
    }

    private function leafKey(string $key, string $group): string
    {
        // broadcast.min_radius_km -> min_radius_km (when group is broadcast)
        $prefix = $group . '.';
        return str_starts_with($key, $prefix) ? substr($key, strlen($prefix)) : $key;
    }

    private function cast(?string $value, string $type, mixed $default): mixed
    {
        if ($value === null) return $default;

        return match ($type) {
            'int' => (int) $value,
            'float' => (float) $value,
            'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool) $default,
            'json' => json_decode($value, true) ?? $default,
            default => $value,
        };
    }

    private function stringify(mixed $value, string $type): ?string
    {
        if ($value === null) return null;

        return match ($type) {
            'json' => json_encode($value, JSON_UNESCAPED_UNICODE),
            'bool' => $value ? 'true' : 'false',
            default => (string) $value,
        };
    }
}
