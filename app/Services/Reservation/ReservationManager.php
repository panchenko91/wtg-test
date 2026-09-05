<?php

namespace App\Services\Reservation;

use App\Models\Offer;
use App\Models\Reservation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ReservationManager
{
    public function reserve($client, Offer $offer)
    {
        $this->validateClientData($client);

        return DB::transaction(function () use ($client, $offer) {
            $existing = Reservation::query()->where('client_reference', data_get($client, 'client_reference'))->first();

            if ($existing) {
                return $existing;
            }

            $locked = Offer::query()->whereKey(model_id($offer))
                ->lockForUpdate()
                ->first();

            if (! $locked || $locked->available_units < 1) {
                abort(409, __('No units left'));
            }

            $locked->decrement('available_units');

            return $locked->reservations()->create($client);
        });
    }

    protected function validateClientData($data)
    {
        return Validator::make($data, [
            'client_reference' => 'required',
            'customer_name' => 'required',
            'customer_email' => 'required',
        ])->validate();
    }
}
