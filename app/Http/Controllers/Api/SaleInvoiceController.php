<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SaleInvoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\DrawerTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SaleInvoiceController extends Controller
{
    public function store(Request $request)
    {
        // 1. التأكد من إن البيانات جاية صح من الفرونت إند
        $request->validate([
            'client_id' => 'nullable|exists:clients,id', // العميل اختياري (ممكن زبون طياري)
            'paid_amount' => 'required|numeric|min:0', // الفلوس اللي ادفعت كاش
            'items' => 'required|array|min:1', // لازم الفاتورة يكون فيها صنف واحد على الأقل
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        try {
            // بدأ المعاملة المالية (يا تتنفذ كلها، يا تتلغي كلها)
            DB::beginTransaction();

            $total_amount = 0;

            // 2. إنشاء رأس الفاتورة مبدئياً بإجمالي 0
            $invoice = SaleInvoice::create([
                'user_id' => 1, // مؤقتاً هنسجلها باسم الأدمن لحد ما نعمل الـ Login
                'client_id' => $request->client_id,
                'total_amount' => 0,
                'paid_amount' => $request->paid_amount,
            ]);

            // 3. لفة على الأصناف اللي العميل اشتراها
            foreach ($request->items as $item) {
                // بنجيب بيانات المنتج من الداتا بيز عشان نضمن إن سعره حقيقي مش مبعوت ملعوب فيه من الفرونت
                $product = Product::findOrFail($item['product_id']);

                // التأكد من إن المخزن فيه كمية تكفي
                if ($product->stock_quantity < $item['quantity']) {
                    throw new \Exception("عفواً، الكمية المتاحة من {$product->name} لا تكفي.");
                }

                $subtotal = $product->selling_price * $item['quantity'];
                $total_amount += $subtotal; // تجميع إجمالي الفاتورة

                // تسجيل الصنف جوه الفاتورة
                InvoiceItem::create([
                    'sale_invoice_id' => $invoice->id,
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'selling_price' => $product->selling_price, // بنسجل السعر الحالي عشان لو اتغير بعدين
                    'subtotal' => $subtotal,
                ]);

                // خصم الكمية من المخزن
                $product->decrement('stock_quantity', $item['quantity']);
            }

            // 4. تحديث إجمالي الفاتورة النهائي
            $invoice->update(['total_amount' => $total_amount]);

            // 5. رمي الفلوس اللي ادفعت في الخزنة
            if ($request->paid_amount > 0) {
                DrawerTransaction::create([
                    'user_id' => 1, // مؤقتاً
                    'type' => 'in',
                    'amount' => $request->paid_amount,
                    'description' => "مبيعات فاتورة رقم {$invoice->id}",
                ]);
            }

            // تأكيد العمليات كلها وحفظها في الداتا بيز
            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'تم تسجيل الفاتورة بنجاح',
                // بنرجع الفاتورة ومعاها الأصناف بتاعتها عشان تنطبع
                'data' => $invoice->load('items.product')
            ], 201);
        } catch (\Exception $e) {
            // لو حصل أي إيرور (زي المخزن مش مكفي)، بنعمل تراجع عن كل حاجة
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }
}
