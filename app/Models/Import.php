<?php

namespace App\Models;

use App\Enums\ImportStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['status', 'supplier_id', 'raw_offers', 'error', 'sent_at', 'completed_at'])]
class Import extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'raw_offers' => 'array',
            'sent_at' => 'datetime',
            'completed_at' => 'datetime',
            'status' => ImportStatus::class,
        ];
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function getTotalOffersAttribute(): int
    {
        return count($this->raw_offers);
    }

    public function completed()
    {
        $this->fill([
            'completed_at' => now(),
            'status' => ImportStatus::Completed,
        ])->save();
    }
}
