<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductVariation extends Model
{
    protected $fillable = [
        'product_id', 'sku', 'attributes', 'price',
        'sale_price', 'stock_quantity', 'image_url'
    ];

    protected $casts = [
        'attributes' => 'array'
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}