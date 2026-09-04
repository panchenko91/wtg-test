<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OfferResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'supplier' => SupplierResource::make($this->whenLoaded('supplier')),
            'price' => $this->resource->price,
            'currency' => $this->resource->currency,
            'available_units' => $this->resource->available_units,
            'expires_at' => $this->resource->expires_at,
        ];
    }
}
