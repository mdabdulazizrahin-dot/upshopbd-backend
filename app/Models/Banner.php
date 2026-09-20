<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Banner extends Model
{
    protected $fillable = [
        'title', 'subtitle', 'image_url', 'link_url',
        'button_text', 'link', 'status', 'sort_order', 'is_active'
    ];
    protected $casts = [
        'is_active' => 'boolean',
    ];
}
