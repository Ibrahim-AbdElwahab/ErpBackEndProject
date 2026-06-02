<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaleInvoice extends Model
{
    protected $fillable = ['user_id', 'client_id', 'total_amount', 'paid_amount'];

    // الفاتورة فيها أصناف (عناصر) كتير
    public function items()
    {
        return $this->hasMany(InvoiceItem::class);
    }

    // الفاتورة دي بتاعة عميل معين
    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    // الفاتورة دي اللي عملها مستخدم (محاسب) معين
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
