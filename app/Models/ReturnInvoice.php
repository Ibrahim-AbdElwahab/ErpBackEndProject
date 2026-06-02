<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReturnInvoice extends Model
{
    protected $fillable = ['user_id', 'client_id', 'total_amount', 'return_type'];

    public function items()
    {
        return $this->hasMany(ReturnItem::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}
