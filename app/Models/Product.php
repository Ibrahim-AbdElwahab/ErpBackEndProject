<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'category_id',
        'name',
        'barcode',
        'purchase_price',
        'selling_price',
        'stock_quantity'
    ];

    // المنتج بينتمي لتصنيف واحد
    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
