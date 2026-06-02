<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DrawerTransaction extends Model
{
    protected $fillable = ['user_id', 'type', 'amount', 'description'];

    // العملية دي اللي عملها مستخدم معين
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
