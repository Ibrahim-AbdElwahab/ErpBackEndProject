<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseInvoice extends Model
{
    protected $guarded = [];

    // ضيف الدالة دي جوه الكلاس
    public function items()
    {
        // الفاتورة الواحدة ليها أكثر من صنف
        return $this->hasMany(PurchaseInvoiceItem::class, 'purchase_invoice_id', 'id');
    }
}
