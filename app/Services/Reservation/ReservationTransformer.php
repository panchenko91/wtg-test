<?php

namespace App\Services\Reservation;

class ReservationTransformer
{
    public static function instance(): static
    {
        return app(static::class);
    }

    public function transform($data): array
    {
        return [
            'client_reference' => data_get($data, 'client_reference'),
            'customer_name' => data_get($data, 'customer_name'),
            'customer_email' => data_get($data, 'customer_email'),
        ];
    }
}
