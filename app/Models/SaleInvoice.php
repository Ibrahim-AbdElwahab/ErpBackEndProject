<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SaleInvoice extends Model
{
    use HasFactory;

    protected $table = 'sale_invoices';

    // حطينا guarded فاضية عشان لارفل يقبل يحفظ كل الحقول براحته وميضربش إيرور fillable تاني
    protected $guarded = [];

    public function items()
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}
