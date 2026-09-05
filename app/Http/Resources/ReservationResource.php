<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReservationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $attributes = [
            'id' => $this->resource->id,
            'client_reference' => $this->resource->client_reference,
            'customer_name' => $this->resource->customer_name,
            'customer_email' => $this->resource->customer_email,
            'created_at' => $this->resource->created_at,
        ];

        if ($this->resource->relationLoaded('offer')) {
            $attributes = array_merge($attributes, [
                'check_in' => $this->resource->offer->check_in,
                'check_out' => $this->resource->offer->check_out,
                'property' => PropertyResource::make($this->whenLoaded('offer')->property),
            ]);
        }

        return $attributes;
    }
}
