<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model
{
    use HasFactory;

    protected $table = 'invoice_items';

    // السطر السحري اللي بيلغي حماية لارفل المزعجة ويخليه يقبل كل الحقول
    protected $guarded = [];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
