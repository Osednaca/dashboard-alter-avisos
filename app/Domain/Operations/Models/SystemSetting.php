<?php

namespace App\Domain\Operations\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['key', 'value', 'group', 'label'])]
class SystemSetting extends Model
{
    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = static::query()->where('key', $key)->first();

        return $setting?->value['data'] ?? $default;
    }

    public static function put(string $key, mixed $value, string $group = 'general', ?string $label = null): void
    {
        static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => ['data' => $value], 'group' => $group, 'label' => $label ?? $key],
        );
    }
}
