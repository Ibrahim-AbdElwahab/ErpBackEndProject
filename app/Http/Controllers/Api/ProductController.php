<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    // عرض الأصناف
    public function index()
    {
        $products = Product::all();

        // التغليف ده مهم جداً للفرونت إند عشان ميضربش Undefined
        return response()->json([
            'status' => 'success',
            'data' => $products
        ], 200);
    }

    // حفظ صنف جديد
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'category_id' => 'required',
            'purchase_price' => 'required|numeric',
        ]);

        $data = $request->all();

        // ربط السعر الوارد من الفرونت إند بحقل الداتا بيز
        $data['selling_price'] = $request->input('sale_price', $request->purchase_price);
        $data['stock_quantity'] = $request->stock_quantity ?? 0;

        $product = Product::create($data);

        return response()->json([
            'status' => 'success',
            'message' => 'تم الحفظ بنجاح',
            'data' => $product
        ], 201);
    }

    // تعديل بيانات صنف موجود
    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string',
            'category_id' => 'required',
            'purchase_price' => 'required|numeric',
        ]);

        $product = Product::findOrFail($id);
        $data = $request->all();

        // ربط السعر زي ما عملنا في الإضافة
        if ($request->has('sale_price')) {
            $data['selling_price'] = $request->sale_price;
        }

        $product->update($data);

        return response()->json([
            'status' => 'success',
            'message' => 'تم تعديل الصنف بنجاح',
            'data' => $product
        ]);
    }

    // حذف صنف
    public function destroy($id)
    {
        $product = Product::findOrFail($id);
        $product->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'تم حذف الصنف'
        ]);
    }
}
