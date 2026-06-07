<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseInvoiceItem extends Model
{
    protected $guarded = [];

    // ضيف الدالة دي جوه الكلاس
    public function product()
    {
        // الصنف ده مربوط بمنتج معين في المخزن
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }
}
