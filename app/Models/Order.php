<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'order_number', 'user_id', 'ip_address', 'customer_name', 'customer_phone', 'customer_email',
        'delivery_address', 'district', 'upazila', 'order_note',
        'delivery_type', 'delivery_charge', 'subtotal', 'total_amount',
        'payment_method', 'payment_status', 'order_status',
        'is_suspicious', 'suspicious_reason',
        'courier_name', 'tracking_number', 'courier_tracking_link',
    ];

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
