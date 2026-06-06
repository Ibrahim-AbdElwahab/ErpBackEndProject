<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    // 1. عرض كل الأقسام
    public function index()
    {
        $categories = Category::all();
        return response()->json([
            'status' => 'success',
            'data' => $categories
        ]);
    }

    // 2. إضافة قسم جديد
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:categories,name'
        ]);

        $category = Category::create($request->all());

        return response()->json([
            'status' => 'success',
            'message' => 'تم إضافة القسم بنجاح',
            'data' => $category
        ], 201);
    }

    // 3. تعديل اسم القسم (الزرار اللي إنت محتاجه)
    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:categories,name,' . $id
        ]);

        $category = Category::findOrFail($id);
        $category->name = $request->name;
        $category->save();

        return response()->json([
            'status' => 'success',
            'message' => 'تم تعديل اسم القسم بنجاح وتحديثه في المخزن كله',
            'data' => $category
        ]);
    }
}
