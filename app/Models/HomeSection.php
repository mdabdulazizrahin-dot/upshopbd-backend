<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HomeSection extends Model
{
    protected $fillable = [
        'section_key',
        'title',
        'is_visible',
        'sort_order',
        'settings',
    ];

    protected $casts = [
        'is_visible' => 'boolean',
        'settings' => 'array',
    ];
}
