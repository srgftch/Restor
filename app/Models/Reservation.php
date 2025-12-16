<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reservation extends Model
{
    protected $fillable = ['user_id', 'table_id', 'date_time', 'status', 'price'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function table()
    {
        return $this->belongsTo(Table::class);
    }

    public function foods()
    {
        return $this->belongsToMany(Food::class)
            ->withPivot(['quantity'])
            ->withTimestamps();
    }
}

