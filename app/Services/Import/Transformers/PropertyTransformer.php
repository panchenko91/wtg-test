<?php

namespace App\Services\Import\Transformers;

use App\Models\Import;

class PropertyTransformer
{
    public static function instance(): static
    {
        return app(static::class);
    }

    public function transform($data): array
    {
        return [
            'name' => data_get($data, 'name'),
            'code' => data_get($data, 'code'),
            'city' => data_get($data, 'city'),
        ];
    }
}
