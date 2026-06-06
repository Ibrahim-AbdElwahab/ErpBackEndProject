<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReturnItem extends Model
{
    protected $guarded = [];

    public function invoice()
    {
        return $this->belongsTo(ReturnInvoice::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
