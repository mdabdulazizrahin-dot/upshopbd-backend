<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AbandonedCheckout extends Model
{
    protected $fillable = [
        'customer_name', 'customer_phone', 'customer_email',
        'district', 'upazila', 'delivery_address',
        'subtotal', 'delivery_charge', 'total_amount',
        'items', 'session_id', 'last_activity',
    ];

    protected $casts = [
        'items' => 'array',
        'last_activity' => 'datetime',
    ];
}
