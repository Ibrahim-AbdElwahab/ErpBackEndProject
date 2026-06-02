<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ReturnInvoice;
use App\Models\ReturnItem;
use App\Models\Product;
use App\Models\Client;
use App\Models\DrawerTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReturnController extends Controller
{
    public function store(Request $request)
    {
        // 1. التأكد من البيانات
        $request->validate([
            'client_id' => 'nullable|exists:clients,id',
            'return_type' => 'required|in:cash,credit', // كاش ولا رصيد؟
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        // لو المرتجع هينزل في الرصيد، لازم يكون في عميل متحدد مينفعش يكون زبون طياري
        if ($request->return_type === 'credit' && !$request->client_id) {
            return response()->json([
                'status' => 'error',
                'message' => 'يجب تحديد العميل في حالة المرتجع الآجل (إضافة للرصيد)'
            ], 400);
        }

        try {
            DB::beginTransaction();
            $total_amount = 0;

            // 2. إنشاء رأس فاتورة المرتجع
            $invoice = ReturnInvoice::create([
                'user_id' => 1, // مؤقتاً لحد ما نعمل نظام الدخول
                'client_id' => $request->client_id,
                'return_type' => $request->return_type,
                'total_amount' => 0,
            ]);

            // 3. لفة على الأصناف اللي رجعت
            foreach ($request->items as $item) {
                $product = Product::findOrFail($item['product_id']);
                $subtotal = $product->selling_price * $item['quantity'];
                $total_amount += $subtotal;

                // تسجيل الصنف في الفاتورة
                ReturnItem::create([
                    'return_invoice_id' => $invoice->id,
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'price' => $product->selling_price,
                    'subtotal' => $subtotal,
                ]);

                // 🔴 البضاعة رجعت المخزن (هنزود الكمية بدل ما كنا بننقصها في المبيعات)
                $product->increment('stock_quantity', $item['quantity']);
            }

            // تحديث الإجمالي
            $invoice->update(['total_amount' => $total_amount]);

            // 4. توجيه الفلوس (الدرج ولا رصيد العميل؟)
            if ($request->return_type === 'cash') {
                // العميل أخد فلوسه كاش ومشي -> الفلوس خرجت من الدرج
                DrawerTransaction::create([
                    'user_id' => 1,
                    'type' => 'out', // Out يعني فلوس طالعة من المحل
                    'amount' => $total_amount,
                    'description' => "مرتجع نقدي لفاتورة مرتجعات رقم {$invoice->id}",
                ]);
            } elseif ($request->return_type === 'credit') {
                // العميل ساب الفلوس في حسابه -> رصيده هيزيد
                $client = Client::findOrFail($request->client_id);
                $client->balance += $total_amount;
                $client->save();
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'تم تسجيل المرتجع بنجاح وتحديث الحسابات والمخزن',
                'data' => $invoice->load('items.product')
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }
}
