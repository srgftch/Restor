<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Food extends Model
{
    protected $fillable = [
        'name',
        'image_url',
        'calories',
        'ingredients',
        'price',
    ];

    public function reservations()
    {
        return $this->belongsToMany(Reservation::class)
            ->withPivot(['quantity'])
            ->withTimestamps();
    }
}

