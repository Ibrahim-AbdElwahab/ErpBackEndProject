<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index()
    {
        return response()->json(Product::all(), 200);
    }

    public function store(Request $request)
    {
        // 1. التحقق من البيانات الأساسية
        $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'required|integer',
            'purchase_price' => 'required|numeric',
        ]);

        // 2. تجهيز البيانات
        $data = $request->all();

        // 3. ربط السعر (الفرونت بيبعت sale_price، الداتا بيز بتطلب selling_price)
        $data['selling_price'] = $request->input('sale_price', 0);

        // 4. الحفظ
        $product = Product::create($data);

        return response()->json([
            'message' => 'تم إضافة الصنف بنجاح',
            'product' => $product
        ], 201);
    }
}
