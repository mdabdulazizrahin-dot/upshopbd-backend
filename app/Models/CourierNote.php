<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourierNote extends Model
{
    protected $fillable = ['order_id', 'source', 'message', 'created_by'];
}
