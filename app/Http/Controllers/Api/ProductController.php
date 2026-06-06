<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index()
    {
        return response()->json(Product::all());
    }

    public function store(Request $request)
    {
        // 1. فك الـ Required من الـ selling_price عشان نتحكم فيه يدوياً
        $request->validate([
            'name' => 'required|string',
            'category_id' => 'required',
            'purchase_price' => 'required|numeric',
        ]);

        $data = $request->all();

        // 2. التحويل الذكي: عشان ما يضربش إيرور 1364
        $data['selling_price'] = $request->input('sale_price', $request->purchase_price);

        // 3. تأكد إن القيم دي موجودة
        $data['stock_quantity'] = $request->stock_quantity ?? 0;

        $product = Product::create($data);

        return response()->json(['message' => 'تم الحفظ', 'product' => $product], 201);
    }
}
