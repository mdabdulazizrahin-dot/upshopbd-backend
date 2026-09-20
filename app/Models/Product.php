<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'name', 'description', 'product_type', 'price', 'sale_price',
        'stock_quantity', 'category_id', 'status', 'seo_title',
        'seo_description', 'seo_slug',
    ];

    protected $casts = [
        'price' => 'float',
        'sale_price' => 'float',
        'stock_quantity' => 'integer',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    public function variations()
    {
        return $this->hasMany(ProductVariation::class);
    }
}