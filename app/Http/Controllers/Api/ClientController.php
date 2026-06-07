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

    // 2. إضافة عميل جديد
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'balance' => 'numeric'
        ]);

        $client = Client::create($request->all());

        return response()->json([
            'status' => 'success',
            'message' => 'تم إضافة العميل بنجاح',
            'data' => $client
        ], 201);
    }

    // 3. تسجيل دفعة نقدية
    public function pay(Request $request, $id)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1'
        ]);

        try {
            DB::beginTransaction();

            $client = Client::findOrFail($id);
            $client->balance += $request->amount;
            $client->save();

            DrawerTransaction::create([
                'user_id' => 1,
                'type' => 'in',
                'amount' => $request->amount,
                'description' => "دفعة مسددة من العميل: {$client->name}",
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'تم تسجيل الدفعة بنجاح',
                'data' => $client
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    // 4. التعديل وإشعارات التسوية (الجوكر)
    public function update(Request $request, $id)
    {
        $client = Client::findOrFail($id);

        $client->name = $request->name ?? $client->name;
        $client->phone = $request->phone ?? $client->phone;

        if ($request->filled('adjustment_amount') && $request->adjustment_amount > 0) {
            $amount = $request->adjustment_amount;
            $note = $request->adjustment_note ?? 'تسوية حساب';

            if ($request->adjustment_type === 'discount') {
                $client->balance += $amount;
                DrawerTransaction::create([
                    'user_id' => 1,
                    'type' => 'adjustment',
                    'amount' => $amount,
                    'description' => "إشعار خصم للعميل: {$client->name} - {$note}",
                ]);
            } elseif ($request->adjustment_type === 'addition') {
                $client->balance -= $amount;
                DrawerTransaction::create([
                    'user_id' => 1,
                    'type' => 'adjustment',
                    'amount' => $amount,
                    'description' => "إشعار إضافة مديونية للعميل: {$client->name} - {$note}",
                ]);
            }
        }

        $client->save();

        return response()->json([
            'status' => 'success',
            'message' => 'تم التعديل والتسوية بنجاح',
            'data' => $client
        ]);
    }

    // 5. كشف الحساب المتكامل
    public function statement($id)
    {
        $client = \App\Models\Client::findOrFail($id);
        $statement = [];
        $balance = 0;

        // 1. جلب فواتير المبيعات
        $sales = \App\Models\SaleInvoice::where('client_id', $id)->orderBy('created_at')->get();

        foreach ($sales as $sale) {
            $balance += $sale->total_amount; // زيادة المديونية

            $statement[] = [
                'id' => $sale->id,
                'invoice_id' => $sale->id, // 👈 السطر ده هو اللي كان ناقص
                'date' => $sale->created_at->format('Y-m-d h:i A'),
                'type' => 'فاتورة مبيعات',
                'description' => 'فاتورة مبيعات رقم #' . $sale->id,
                'debit' => $sale->total_amount, // مدين (عليه)
                'credit' => 0,
                'balance' => $balance,
            ];
        }

        // (لو عندك كود بيجيب الدفعات/السداد حطه هنا بنفس الطريقة بس الـ credit هو اللي بيزيد)

        return response()->json([
            'status' => 'success',
            'client_name' => $client->name,
            'current_balance' => $balance,
            'statement' => $statement
        ]);
    }
}
