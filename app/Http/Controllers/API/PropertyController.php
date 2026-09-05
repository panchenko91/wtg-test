<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\API\SearchPropertyRequest;
use App\Http\Resources\PropertyResource;
use App\Models\Property;

class PropertyController extends Controller
{
    protected $perPage = 10;

    public function search(SearchPropertyRequest $request)
    {
        $checkIn = $request->date('check_in');
        $checkOut = $request->date('check_out');
        $guests = $request->integer('guests');
        $city = $request->input('city');

        $filter = function ($query) use ($checkIn, $checkOut, $guests) {
            $query->where('available_units', '>', 0)
                ->where('expires_at', '>', now())
                ->when($checkIn, fn ($query) => $query->where('check_in', $checkIn))
                ->when($checkOut, fn ($query) => $query->where('check_out', $checkOut))
                ->when($guests, fn ($query) => $query->where('max_guests', '>=', $guests));
        };

        $properties = Property::query()
            ->when($city, fn ($query) => $query->where('city', $city))
            ->whereHas('offers', $filter)
            ->with(['offers' => fn ($query) => $query->cheapestPerProperty($filter)
            ->with('supplier')]);

        return PropertyResource::collection($properties->paginate($this->perPage));
    }
}
