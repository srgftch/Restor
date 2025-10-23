<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'user_id',
        'reservation_id',
        'amount_rubles',
        'currency',
        'status',
        'provider_reference',
        'card_brand',
        'card_last4',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function user() { return $this->belongsTo(\App\Models\User::class); }
    public function reservation() { return $this->belongsTo(\App\Models\Reservation::class); }
}
