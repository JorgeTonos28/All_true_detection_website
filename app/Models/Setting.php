<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Throwable;

#[Fillable(['key', 'label', 'value', 'group', 'type'])]
class Setting extends Model
{
    public static function values(): array
    {
        try {
            return static::query()
                ->pluck('value', 'key')
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    public static function put(string $key, ?string $value): void
    {
        static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value],
        );
    }
}
