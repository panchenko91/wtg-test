<?php

namespace App\Http\Controllers\API;

use App\Exceptions\OfferUnavailableException;
use App\Http\Controllers\Controller;
use App\Http\Requests\API\OfferReservationRequest;
use App\Http\Resources\ReservationResource;
use App\Models\Offer;
use App\Services\Reservation\ReservationManager;
use App\Services\Reservation\ReservationTransformer;

class OfferController extends Controller
{
    public function reservation(Offer $offer, OfferReservationRequest $request)
    {
        $transformedData = ReservationTransformer::instance()->transform($request->validated());

        try {
            $reservation = $this->reservationManager()->reserve($transformedData, $offer);
        } catch (OfferUnavailableException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        $status = $reservation->wasRecentlyCreated ? 201 : 200;

        $reservation->load(['offer', 'offer.property']);

        return ReservationResource::make($reservation)
            ->response()
            ->setStatusCode($status);
    }

    protected function reservationManager()
    {
        return resolve(ReservationManager::class);
    }
}
