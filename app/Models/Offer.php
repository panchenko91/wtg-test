<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

#[Fillable(['import_id', 'external_id', 'property_id', 'supplier_id', 'check_in', 'check_out', 'price', 'currency', 'available_units', 'max_guests', 'expires_at'])]
class Offer extends Model
{
    use HasFactory;

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

    public function scopeActive(Builder $query)
    {
        return $query
            ->where('available_units', '>', 0)
            ->where('expires_at', '>', now());
    }

    public function scopeMatching(Builder $query, $checkIn = null, $checkOut = null, ?int $guests = null)
    {
        return $query
            ->when($checkIn, fn ($query) => $query->where('check_in', $checkIn))
            ->when($checkOut, fn ($query) => $query->where('check_out', $checkOut))
            ->when($guests, fn ($query) => $query->where('max_guests', '>=', $guests));
    }

    public function scopeCheapestPerProperty($builder, $callback = null)
    {
        return $builder->whereExists(function ($builder) use ($callback) {
            $sub = DB::table('offers as o')
                ->select('id')
                ->whereColumn('o.property_id', 'offers.property_id')
                ->when($callback, $callback)
                ->orderBy('o.price')
                ->limit(1);

            $builder->fromSub($sub, 'q')->whereColumn('q.id', 'offers.id');
        });
    }
}
