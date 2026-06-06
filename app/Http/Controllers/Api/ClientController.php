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
        $client = Client::findOrFail($id);

        $invoices = \App\Models\SaleInvoice::where('client_id', $id)->get()->map(function ($invoice) {
            return [
                'date' => $invoice->created_at->format('Y-m-d h:i A'),
                'type' => 'فاتورة مبيعات',
                'description' => 'فاتورة رقم #' . $invoice->id,
                'debit' => $invoice->total_amount,
                'credit' => $invoice->paid_amount,
                'created_at' => $invoice->created_at
            ];
        });

        $payments = DrawerTransaction::where('description', 'LIKE', "%دفعة مسددة من العميل: {$client->name}%")->get()->map(function ($payment) {
            return [
                'date' => $payment->created_at->format('Y-m-d h:i A'),
                'type' => 'سداد نقدي',
                'description' => 'دفعة مسددة بالخزنة',
                'debit' => 0,
                'credit' => $payment->amount,
                'created_at' => $payment->created_at
            ];
        });

        $returns = \App\Models\ReturnInvoice::where('client_id', $id)->get()->map(function ($ret) {
            return [
                'date' => $ret->created_at->format('Y-m-d h:i A'),
                'type' => 'مرتجع مبيعات',
                'description' => 'مرتجع رقم #' . $ret->id,
                'debit' => 0,
                'credit' => $ret->total_amount - $ret->paid_amount,
                'created_at' => $ret->created_at
            ];
        });

        // 🌟 سحب إشعارات الخصم والإضافة
        $adjustments = DrawerTransaction::where('type', 'adjustment')
            ->where('description', 'LIKE', "%للعميل: {$client->name} -%")->get()->map(function ($adj) {
                $isDiscount = str_contains($adj->description, 'خصم');
                return [
                    'date' => $adj->created_at->format('Y-m-d h:i A'),
                    'type' => $isDiscount ? 'إشعار خصم' : 'إشعار إضافة',
                    'description' => $adj->description,
                    'debit' => $isDiscount ? 0 : $adj->amount,
                    'credit' => $isDiscount ? $adj->amount : 0,
                    'created_at' => $adj->created_at
                ];
            });

        $statement = collect($invoices)->merge($payments)->merge($returns)->merge($adjustments)->sortBy('created_at')->values();

        $totalCredit = $statement->sum('credit');
        $totalDebit = $statement->sum('debit');
        $openingBalance = $client->balance - ($totalCredit - $totalDebit);

        $runningBalance = $openingBalance;
        $finalStatement = $statement->map(function ($item) use (&$runningBalance) {
            $runningBalance += ($item['credit'] - $item['debit']);
            $item['balance'] = $runningBalance;
            return $item;
        });

        if ($openingBalance != 0) {
            $finalStatement->prepend([
                'date' => '-',
                'type' => 'رصيد افتتاحي',
                'description' => 'رصيد بداية التعامل',
                'debit' => $openingBalance < 0 ? abs($openingBalance) : 0,
                'credit' => $openingBalance > 0 ? $openingBalance : 0,
                'balance' => $openingBalance
            ]);
        }

        return response()->json([
            'status' => 'success',
            'client_name' => $client->name,
            'current_balance' => $client->balance,
            'statement' => $finalStatement
        ]);
    }
}
