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
        'verified_at',
        'processed_at',
    ];

    protected $casts = [
        'meta' => 'array',
        'amount_rubles' => 'integer',
        'verified_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }
}
