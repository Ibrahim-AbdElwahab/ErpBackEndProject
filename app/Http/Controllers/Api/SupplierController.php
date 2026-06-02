<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Models\DrawerTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SupplierController extends Controller
{
    // 1. عرض كل الموردين
    public function index()
    {
        $suppliers = Supplier::all();
        return response()->json([
            'status' => 'success',
            'data' => $suppliers
        ]);
    }

    // 2. إضافة مورد جديد (الشركات اللي بتجيب منها بضاعة)
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'company_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'balance' => 'numeric' // الرصيد اللي ليه أو عليه
        ]);

        $supplier = Supplier::create($request->all());

        return response()->json([
            'status' => 'success',
            'message' => 'تم إضافة المورد بنجاح',
            'data' => $supplier
        ], 201);
    }

    // 3. تسجيل دفعة صادرة (إنت بتسدد فلوس للمورد من اللي عليك)
    public function pay(Request $request, $id)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1'
        ]);

        try {
            DB::beginTransaction();

            $supplier = Supplier::findOrFail($id);

            // إنت بتسددله، يعني رصيده (اللي كان بالسالب كدين عليك) هيزيد ويقرب للصفر
            $supplier->balance += $request->amount;
            $supplier->save();

            // الفلوس دي هتخرج من الدرج/الخزنة
            DrawerTransaction::create([
                'user_id' => 1, // مؤقتاً
                'type' => 'out', // ركز هنا: الفلوس خارجة من المحل
                'amount' => $request->amount,
                'description' => "دفعة مسددة للمورد: {$supplier->name}",
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'تم سحب الدفعة من الخزنة وتسديدها للمورد بنجاح',
                'data' => $supplier
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }
}
