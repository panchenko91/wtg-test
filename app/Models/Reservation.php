<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['offer_id', 'client_reference', 'customer_name', 'customer_email'])]
class Reservation extends Model
{
    public function offer()
    {
        return $this->belongsTo(Offer::class);
    }
}
