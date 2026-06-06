<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use HasFactory;

    // عشان نسمح بحفظ البيانات في الداتا بيز
    protected $guarded = [];
}
