<?php

namespace App\Services\Import;

use App\Models\Import;
use App\Models\Offer;
use App\Models\Property;
use App\Services\Import\Transformers\OfferTransformer;
use App\Services\Import\Transformers\PropertyTransformer;
use Illuminate\Support\Facades\Validator;

class OfferImportService
{
    public function populateDatabase($data, Import $import)
    {
        $property = $this->createOrUpdateProperty(data_get($data, 'property'));
        $this->createOrUpdateOffer($data, $import, $property);
    }

    protected function createOrUpdateProperty($propertyData)
    {
        $transformed = PropertyTransformer::instance()->transform($propertyData);
        $validated = $this->validatePropertyData($transformed);

        $property = Property::query()
            ->where('code', data_get($transformed, 'code'))
            ->firstOrNew();

        $property->fill($validated)->save();

        return $property;
    }

    protected function createOrUpdateOffer($offerData, Import $import, Property $property)
    {
        $transformed = OfferTransformer::instance()->transform($offerData);
        $validated = array_merge($this->validateOfferData($transformed), [
            'import_id' => model_id($import),
            'property_id' => model_id($property),
        ]);

        Offer::query()->updateOrCreate(
            [
                'supplier_id' => $import->supplier_id,
                'external_id' => data_get($transformed, 'external_id')
            ],
            $validated
        );
    }

    protected function validatePropertyData($data)
    {
        return Validator::make($data, [
            'name' => 'required',
            'code' => 'required',
            'city' => 'required',
        ])->validate();
    }

    protected function validateOfferData($data)
    {
        return Validator::make($data, [
            'external_id' => 'required',
            'check_in' => 'required',
            'check_out' => 'required',
            'max_guests' => 'required',
            'price' => 'required',
            'currency' => 'required',
            'available_units' => 'required',
            'expires_at' => 'required',
        ])->validate();
    }
}
