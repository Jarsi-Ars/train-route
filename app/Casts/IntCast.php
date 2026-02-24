<?php

namespace App\Casts;

use Spatie\LaravelData\Casts\Cast;
use Spatie\LaravelData\Support\DataProperty;
use Spatie\LaravelData\Support\Creation\CreationContext;

class IntCast implements Cast
{
    public function cast(DataProperty $property, mixed $value, array $properties, CreationContext $context): int|string
    {
        return is_numeric($value) ? (int)$value : $value;
    }
}
