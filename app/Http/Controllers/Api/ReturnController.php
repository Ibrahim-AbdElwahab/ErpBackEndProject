<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ReturnInvoice;
use App\Models\ReturnItem;
use App\Models\Product;
use App\Models\Client;
use App\Models\Supplier;
use App\Models\DrawerTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB; // ضروري للـ Transactions

class ReturnController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'type' => 'required|in:client,supplier', // لازم يحدد نوع المرتجع
            'items' => 'required|array',
        ]);

        try {
            DB::beginTransaction(); // 👈 حماية للعمليات عشان لو ضربت مترجعش الداتا بيز تبوظ

            // 1. حساب إجمالي المرتجع
            $totalAmount = 0;
            foreach ($request->items as $item) {
                $totalAmount += ($item['price'] * $item['quantity']);
            }

            // 2. إنشاء فاتورة المرتجع
            $invoice = ReturnInvoice::create([
                'type' => $request->type,
                'client_id' => $request->type === 'client' ? $request->target_id : null,
                'supplier_id' => $request->type === 'supplier' ? $request->target_id : null,
                'total_amount' => $totalAmount,
                'paid_amount' => $request->paid_amount,
                'user_id' => 1,
            ]);

            // 3. تحديث المخزن وتفاصيل الأصناف
            foreach ($request->items as $item) {
                $product = Product::findOrFail($item['product_id']);

                if ($request->type === 'client') {
                    $product->stock_quantity += $item['quantity']; // العميل رجع بضاعة، فالمخزن بيزيد
                } else {
                    $product->stock_quantity -= $item['quantity']; // رجعنا بضاعة للمورد، فالمخزن بيقل
                }
                $product->save();

                ReturnItem::create([
                    'return_invoice_id' => $invoice->id,
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'subtotal' => $item['price'] * $item['quantity']
                ]);
            }

            // 4. توجيه الخزنة والحسابات الآجلة
            if ($request->type === 'client') {
                // مرتجع مبيعات (عميل رجع بضاعة وخد كاش)
                if ($request->paid_amount > 0) {
                    DrawerTransaction::create([
                        'amount' => $request->paid_amount,
                        'type' => 'out', // الفلوس خرجت من الدرج
                        'description' => 'مرتجع مبيعات نقدي - فاتورة رقم: ' . $invoice->id,
                        'user_id' => 1 // 👈 ده كان ناقص وممكن يضرب الداتا بيز
                    ]);
                }
                if ($request->target_id && $totalAmount > $request->paid_amount) {
                    $client = Client::findOrFail($request->target_id);
                    // العميل ليه فلوس عندنا (مديونيته بتقل)
                    $client->balance += ($totalAmount - $request->paid_amount);
                    $client->save();
                }
            } else {
                // مرتجع مشتريات (رجعنا بضاعة للمورد وأخدنا كاش)
                if ($request->paid_amount > 0) {
                    DrawerTransaction::create([
                        'amount' => $request->paid_amount,
                        'type' => 'in', // المورد رجعلنا فلوس كاش دخلت الدرج
                        'description' => 'مرتجع مشتريات نقدي - فاتورة رقم: ' . $invoice->id,
                        'user_id' => 1
                    ]);
                }
                if ($request->target_id && $totalAmount > $request->paid_amount) {
                    $supplier = Supplier::findOrFail($request->target_id);
                    // الباقي يقلل المديونية اللي علينا للمورد
                    $supplier->balance -= ($totalAmount - $request->paid_amount);
                    $supplier->save();
                }
            }

            DB::commit(); // 👈 كل الخطوات تمام، احفظ الشغل
            return response()->json(['message' => 'تم حفظ المرتجع وتوجيه الحسابات بنجاح']);
        } catch (\Exception $e) {
            DB::rollBack(); // 👈 لو في خطأ، ارجع في كل حاجة
            return response()->json(['message' => 'حدث خطأ: ' . $e->getMessage()], 500);
        }
    }

    // دالة عرض المرتجع للمودال (شغالة 100% بعد إضافة العلاقات للموديلز)
    public function showReturn($id)
    {
        $returnInv = \App\Models\ReturnInvoice::with('items.product')->findOrFail($id);

        $formattedItems = $returnInv->items->map(function ($item) {
            return [
                'id' => $item->id,
                'name' => $item->product ? $item->product->name : 'صنف غير متوفر',
                'quantity' => $item->quantity,
                'price' => $item->price ?? $item->cost_price ?? $item->purchase_price ?? 0,
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => [
                'id' => $returnInv->id,
                'invoice_number' => $returnInv->id,
                'date' => $returnInv->created_at->format('Y-m-d h:i A'),
                'type' => $returnInv->type === 'client' ? 'مرتجع مبيعات' : 'مرتجع مشتريات',
                'total_amount' => $returnInv->total_amount,
                'items' => $formattedItems
            ]
        ]);
    }
}
