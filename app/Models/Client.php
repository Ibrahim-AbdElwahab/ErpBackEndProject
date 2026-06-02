<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    protected $fillable = ['name', 'phone', 'balance'];

    // العميل الواحد ممكن يعمل فواتير مبيعات كتير
    public function invoices()
    {
        return $this->hasMany(SaleInvoice::class);
    }
}
