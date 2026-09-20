<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourierSetting extends Model
{
    protected $fillable = [
        'courier_name', 'display_name', 'api_key', 'api_secret',
        'access_token', 'is_enabled', 'is_default', 'default_weight',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'is_default' => 'boolean',
        'default_weight' => 'float',
    ];
}
