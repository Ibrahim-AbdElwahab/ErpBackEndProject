<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model
{
    protected $fillable = [
        'sale_invoice_id',
        'product_id',
        'quantity',
        'selling_price',
        'subtotal'
    ];

    // العنصر ده بينتمي لفاتورة معينة
    public function invoice()
    {
        return $this->belongsTo(SaleInvoice::class, 'sale_invoice_id');
    }

    // العنصر ده عبارة عن منتج معين
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
