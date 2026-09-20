<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BlockedEntity extends Model
{
    use HasFactory;

    protected $table = 'blocked_entities';

    protected $fillable = [
        'type',   // 'ip' or 'phone'
        'value',
        'reason',
    ];
}
