<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Models\DrawerTransaction;
use App\Models\PurchaseInvoice;
use App\Models\ReturnInvoice;
use Illuminate\Http\Request;

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

    // 2. حفظ مورد جديد
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'balance' => 'numeric'
        ]);

        $supplier = Supplier::create($request->all());

        return response()->json([
            'status' => 'success',
            'message' => 'تم إضافة المورد بنجاح',
            'data' => $supplier
        ], 201);
    }

    // 3. تسجيل دفعة نقدية طالعة مننا للمورد
    public function pay(Request $request, $id)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1'
        ]);

        $supplier = Supplier::findOrFail($id);
        $supplier->balance -= $request->amount;
        $supplier->save();

        DrawerTransaction::create([
            'user_id' => 1,
            'type' => 'out',
            'amount' => $request->amount,
            'description' => "دفعة مسددة للمورد: {$supplier->name}",
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'تم صرف الدفعة من الخزنة بنجاح',
            'new_balance' => $supplier->balance
        ]);
    }

    // 4. التعديل وإشعارات التسوية للموردين
    public function update(Request $request, $id)
    {
        $supplier = Supplier::findOrFail($id);

        $supplier->name = $request->name ?? $supplier->name;
        $supplier->phone = $request->phone ?? $supplier->phone;

        if ($request->filled('adjustment_amount') && $request->adjustment_amount > 0) {
            $amount = $request->adjustment_amount;
            $note = $request->adjustment_note ?? 'تسوية حساب';

            if ($request->adjustment_type === 'discount') {
                $supplier->balance -= $amount;
                DrawerTransaction::create([
                    'user_id' => 1,
                    'type' => 'adjustment',
                    'amount' => $amount,
                    'description' => "إشعار خصم من المورد: {$supplier->name} - {$note}",
                ]);
            } elseif ($request->adjustment_type === 'addition') {
                $supplier->balance += $amount;
                DrawerTransaction::create([
                    'user_id' => 1,
                    'type' => 'adjustment',
                    'amount' => $amount,
                    'description' => "إشعار إضافة مديونية للمورد: {$supplier->name} - {$note}",
                ]);
            }
        }

        $supplier->save();

        return response()->json([
            'status' => 'success',
            'message' => 'تم تعديل وتسوية حساب المورد بنجاح',
            'data' => $supplier
        ]);
    }

    // 5. كشف حساب المورد المتكامل
    public function statement($id)
    {
        $supplier = Supplier::findOrFail($id);

        $invoices = PurchaseInvoice::where('supplier_id', $id)->get()->map(function ($invoice) {
            return [
                'id' => $invoice->id,
                'invoice_id' => $invoice->id, // 👈 السطر ده ضروري عشان المودال يفتح المشتريات
                'date' => $invoice->created_at->format('Y-m-d h:i A'),
                'type' => 'فاتورة مشتريات',
                'description' => 'فاتورة رقم #' . $invoice->id,
                'credit' => $invoice->total_amount,
                'debit' => $invoice->paid_amount,
                'created_at' => $invoice->created_at
            ];
        });

        $payments = DrawerTransaction::where('description', 'LIKE', "%دفعة مسددة للمورد: {$supplier->name}%")->get()->map(function ($payment) {
            return [
                'id' => $payment->id,
                'invoice_id' => null, // دي دفعة نقدية ملهاش موديل أصناف
                'date' => $payment->created_at->format('Y-m-d h:i A'),
                'type' => 'سداد نقدي',
                'description' => 'دفعة مسددة من الخزنة',
                'credit' => 0,
                'debit' => $payment->amount,
                'created_at' => $payment->created_at
            ];
        });

        $returns = ReturnInvoice::where('supplier_id', $id)->get()->map(function ($ret) {
            return [
                'id' => $ret->id,
                'invoice_id' => $ret->id, // 👈 السطر ده ضروري للمرتجعات
                'date' => $ret->created_at->format('Y-m-d h:i A'),
                'type' => 'مرتجع مشتريات',
                'description' => 'مرتجع رقم #' . $ret->id,
                'credit' => 0,
                'debit' => $ret->total_amount - $ret->paid_amount,
                'created_at' => $ret->created_at
            ];
        });

        $adjustments = DrawerTransaction::where('type', 'adjustment')
            ->where('description', 'LIKE', "%للمورد: {$supplier->name} -%")->get()->map(function ($adj) {
                $isDiscount = str_contains($adj->description, 'خصم');
                return [
                    'id' => $adj->id,
                    'invoice_id' => null,
                    'date' => $adj->created_at->format('Y-m-d h:i A'),
                    'type' => $isDiscount ? 'إشعار خصم مكسوب' : 'إشعار مديونية إضافية',
                    'description' => $adj->description,
                    'credit' => $isDiscount ? 0 : $adj->amount,
                    'debit' => $isDiscount ? $adj->amount : 0,
                    'created_at' => $adj->created_at
                ];
            });

        $statement = collect($invoices)->merge($payments)->merge($returns)->merge($adjustments)->sortBy('created_at')->values();

        $totalCredit = $statement->sum('credit');
        $totalDebit = $statement->sum('debit');
        $openingBalance = $supplier->balance - ($totalCredit - $totalDebit);

        $runningBalance = $openingBalance;
        $finalStatement = $statement->map(function ($item) use (&$runningBalance) {
            $runningBalance += ($item['credit'] - $item['debit']);
            $item['balance'] = $runningBalance;
            return $item;
        });

        return response()->json([
            'status' => 'success',
            'supplier_name' => $supplier->name,
            'current_balance' => $supplier->balance,
            'statement' => $finalStatement
        ]);
    }
}
