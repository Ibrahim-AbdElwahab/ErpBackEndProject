<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    // جلب كل الأصناف مع اسم التصنيف بتاعها
    public function index()
    {
        $products = Product::with('category')->get();
        return response()->json([
            'status' => 'success',
            'data' => $products
        ]);
    }

    // إضافة صنف جديد للمخزن
    public function store(Request $request)
    {
        $request->validate([
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:255',
            'barcode' => 'nullable|string|unique:products,barcode',
            'purchase_price' => 'required|numeric|min:0',
            'selling_price' => 'required|numeric|min:0',
            'stock_quantity' => 'required|integer|min:0',
        ]);

        $product = Product::create($request->all());

        return response()->json([
            'status' => 'success',
            'message' => 'تم إضافة الصنف بنجاح',
            'data' => $product
        ], 201);
    }
}
