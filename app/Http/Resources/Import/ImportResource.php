<?php

namespace App\Http\Resources\Import;

use App\Http\Resources\SupplierResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ImportResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'supplier' => SupplierResource::make($this->whenLoaded('supplier')),
            'external_id' => $this->resource->external_id,
            'sent_at' => $this->resource->sent_at,
            'status' => $this->resource->status->asText(),
            'total_offers' => $this->resource->total_offers,
            'total_imported' => $this->resource->total_imported,
            'error' => $this->resource->error,
            'created_at' => $this->resource->created_at,
            'completed_at' => $this->resource->completed_at,
        ];
    }
}
