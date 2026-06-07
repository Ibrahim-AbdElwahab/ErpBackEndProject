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

            // في ClientController (دالة update)
            if ($request->adjustment_type === 'discount') {
                $client->balance += $amount;
                DrawerTransaction::create([
                    'user_id' => 1,
                    'type' => 'in', // 👈 غيرناها لـ in بدل adjustment
                    'amount' => $amount,
                    'description' => "تسوية (خصم) - إشعار خصم للعميل: {$client->name} - {$note}",
                ]);
            } elseif ($request->adjustment_type === 'addition') {
                $client->balance -= $amount;
                DrawerTransaction::create([
                    'user_id' => 1,
                    'type' => 'out', // 👈 غيرناها لـ out بدل adjustment
                    'amount' => $amount,
                    'description' => "تسوية (إضافة) - إشعار إضافة مديونية للعميل: {$client->name} - {$note}",
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

        // 1. جلب فواتير المبيعات
        $sales = \App\Models\SaleInvoice::where('client_id', $id)->get()->map(function ($invoice) {
            return [
                'id' => $invoice->id,
                'invoice_id' => $invoice->id,
                'date' => $invoice->created_at,
                'type' => 'فاتورة مبيعات',
                'description' => 'فاتورة مبيعات رقم #' . $invoice->id,
                'debit' => $invoice->total_amount, // عليه
                'credit' => 0,
            ];
        });

        // 2. جلب مرتجعات المبيعات (العميل رجع بضاعة = رصيده يقل = دائن)
        $returns = \App\Models\ReturnInvoice::where('client_id', $id)->where('type', 'client')->get()->map(function ($ret) {
            return [
                'id' => $ret->id,
                'invoice_id' => $ret->id,
                'date' => $ret->created_at,
                'type' => 'مرتجع مبيعات',
                'description' => 'مرتجع رقم #' . $ret->id,
                'debit' => 0,
                'credit' => $ret->total_amount, // دفعناله (أو خصمنا من اللي عليه)
            ];
        });

        // 3. جلب الدفعات النقدية من الخزنة
        $payments = \App\Models\DrawerTransaction::where('description', 'LIKE', "%العميل: {$client->name}%")
            ->where('type', 'in')->get()->map(function ($payment) {
                return [
                    'id' => $payment->id,
                    'invoice_id' => null,
                    'date' => $payment->created_at,
                    'type' => 'سداد نقدي',
                    'description' => $payment->description,
                    'debit' => 0,
                    'credit' => $payment->amount, // دفع فلوس = دائن
                ];
            });

        // 4. دمج وتنسيق البيانات
        $statement = collect($sales)->merge($returns)->merge($payments)->sortBy('date')->values();

        // حساب الرصيد التراكمي
        $runningBalance = 0;
        $finalStatement = $statement->map(function ($item) use (&$runningBalance) {
            $runningBalance += ($item['debit'] - $item['credit']);
            return [
                'date' => $item['date']->format('Y-m-d h:i A'),
                'type' => $item['type'],
                'description' => $item['description'],
                'debit' => $item['debit'] > 0 ? $item['debit'] : 0,
                'credit' => $item['credit'] > 0 ? $item['credit'] : 0,
                'balance' => $runningBalance, // الرصيد بعد الحركة
                'invoice_id' => $item['invoice_id']
            ];
        });

        return response()->json([
            'status' => 'success',
            'client_name' => $client->name,
            'current_balance' => $runningBalance, // الرصيد النهائي الصحيح
            'statement' => $finalStatement
        ]);
    }
}
