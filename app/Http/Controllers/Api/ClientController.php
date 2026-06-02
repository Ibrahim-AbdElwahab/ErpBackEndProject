<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\DrawerTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClientController extends Controller
{
    // 1. عرض كل العملاء
    public function index()
    {
        $clients = Client::all();
        return response()->json([
            'status' => 'success',
            'data' => $clients
        ]);
    }

    // 2. إضافة عميل جديد (تاجر أو صنايعي)
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'balance' => 'numeric' // ممكن يبدأ وعليه فلوس قديمة فنكتبها بالسالب
        ]);

        $client = Client::create($request->all());

        return response()->json([
            'status' => 'success',
            'message' => 'تم إضافة العميل بنجاح',
            'data' => $client
        ], 201);
    }

    // 3. تسجيل دفعة نقدية (العميل بيسدد فلوس من اللي عليه)
    public function pay(Request $request, $id)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1'
        ]);

        try {
            DB::beginTransaction();

            $client = Client::findOrFail($id);

            // العميل بيدفع فلوس لينا، يعني رصيده هيزيد (لأن السالب معناه مديون ولما يدفع بيقرب للصفر)
            $client->balance += $request->amount;
            $client->save();

            // الفلوس دي لازم تدخل الدرج/الخزنة
            DrawerTransaction::create([
                'user_id' => 1, // مؤقتاً لحد ما نفعل تسجيل الدخول
                'type' => 'in',
                'amount' => $request->amount,
                'description' => "دفعة مسددة من العميل: {$client->name}",
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'تم تسجيل الدفعة وإضافتها للخزنة بنجاح',
                'data' => $client
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
