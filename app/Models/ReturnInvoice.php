<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReturnInvoice extends Model
{
    // شيلنا الـ fillable وحطينا guarded عشان يسمح بتسجيل كل الحقول الجديدة (زي المورد والفلوس)
    protected $guarded = [];

    public function items()
    {
        return $this->hasMany(ReturnItem::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    // ضفنا العلاقة دي عشان لو المرتجع رايح لشركة/مورد
    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }
}
