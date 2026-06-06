<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    // دي الحقول اللي مسموح للـ API يكتب فيها
    protected $fillable = [
        'name',
        'category_id',
        'barcode',
        'purchase_price',
        'selling_price',
        'stock_quantity'
    ];
}
