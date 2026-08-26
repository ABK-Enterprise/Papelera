<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class FlexibleDecimal implements CastsAttributes
{
    /**
     * Cast the given value.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): int|float|null
    {
        if ($value === null) {
            return null;
        }

        $floatVal = (float) $value;

        return (fmod($floatVal, 1.0) === 0.0) ? (int) $floatVal : $floatVal;
    }

    /**
     * Prepare the given value for storage.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?float
    {
        return $value === null ? null : (float) $value;
    }
}
