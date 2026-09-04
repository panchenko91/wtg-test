<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'code', 'city'])]
class Property extends Model
{
    use HasFactory;

    public function offers()
    {
        return $this->hasMany(Offer::class);
    }
}
