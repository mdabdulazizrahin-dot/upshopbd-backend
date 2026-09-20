<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliverySetting extends Model
{
    protected $fillable = [
        'delivery_type',
        'charge',
        'status',
    ];

    protected $casts = [
        'charge' => 'decimal:2',
        'status' => 'boolean',
    ];
}
