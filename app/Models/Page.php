<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    protected $fillable = [
        'slug',
        'title',
        'content',
        'seo_title',
        'seo_description',
        'image_url',
        'page_type',
        'status',
    ];
}
