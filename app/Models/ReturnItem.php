<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReturnItem extends Model
{
    protected $fillable = ['return_invoice_id', 'product_id', 'quantity', 'price', 'subtotal'];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
