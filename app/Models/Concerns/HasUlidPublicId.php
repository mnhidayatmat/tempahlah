<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

trait HasUlidPublicId
{
    public static function bootHasUlidPublicId(): void
    {
        static::creating(function ($model) {
            if (empty($model->public_id)) {
                $model->public_id = (string) Str::ulid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
