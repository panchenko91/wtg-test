<?php

namespace App\Services\Import\Transformers;

use Carbon\Carbon;

class OfferTransformer
{
    public static function instance(): static
    {
        return app(static::class);
    }

    public function transform($data): array
    {
        return [
            'external_id' => data_get($data, 'external_id'),
            'check_in' => $this->formatDate(data_get($data, 'check_in')),
            'check_out' => $this->formatDate(data_get($data, 'check_out')),
            'max_guests' => data_get($data, 'max_guests'),
            'currency' => data_get($data, 'currency'),
            'available_units' => data_get($data, 'available_units'),
            'expires_at' => $this->formatDate(data_get($data, 'expires_at')),
            'price' => data_get($data, 'price'),
        ];
    }

    protected function formatDate($date)
    {
        return Carbon::parse($date)->setTimezone(app_timezone());
    }
}
