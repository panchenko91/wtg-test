<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['import_id', 'external_id', 'property_id', 'supplier_id', 'check_in', 'check_out', 'price', 'currency', 'available_units', 'max_guests', 'expires_at'])]
class Offer extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'check_in' => 'datetime',
            'check_out' => 'datetime',
            'expires_at' => 'datetime',
            'price' => 'integer',
            'max_guests' => 'integer',
            'available_units' => 'integer',
        ];
    }

    public function import()
    {
        return $this->belongsTo(Import::class);
    }

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }
}
