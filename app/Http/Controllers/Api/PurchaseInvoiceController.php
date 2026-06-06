<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceItem;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\DrawerTransaction;
use Illuminate\Http\Request;

class PurchaseInvoiceController extends Controller
{
    public function store(Request $request)
    {
        // 1. حساب الإجمالي
        $totalAmount = 0;
        foreach ($request->items as $item) {
            $totalAmount += ($item['purchase_price'] * $item['quantity']);
        }

        // 2. إنشاء فاتورة المشتريات
        $invoice = PurchaseInvoice::create([
            'supplier_id' => $request->supplier_id,
            'total_amount' => $totalAmount,
            'paid_amount' => $request->paid_amount,
            'user_id' => auth()->id() ?? 1,
        ]);

        // 3. تزويد المخزن وتحديث سعر الشراء الجديد
        foreach ($request->items as $item) {
            $product = Product::findOrFail($item['product_id']);
            $product->stock_quantity += $item['quantity']; // البضاعة زادت في المخزن
            $product->purchase_price = $item['purchase_price']; // تحديث تكلفة الصنف للسعر الجديد
            $product->save();

            PurchaseInvoiceItem::create([
                'purchase_invoice_id' => $invoice->id,
                'product_id' => $product->id,
                'quantity' => $item['quantity'],
                'purchase_price' => $item['purchase_price'],
                'subtotal' => $item['purchase_price'] * $item['quantity']
            ]);
        }

        // 4. الفلوس اللي دفعناها تخرج من الخزنة
        if ($request->paid_amount > 0) {
            DrawerTransaction::create([
                'amount' => $request->paid_amount,
                'type' => 'out', // فلوس خارجة (مصروفات مشتريات)
                'description' => 'مشتريات نقدية - فاتورة رقم: ' . $invoice->id,
                'user_id' => auth()->id() ?? 1
            ]);
        }

        // 5. لو باقي فلوس للمورد، تتسجل كدين علينا
        if ($request->supplier_id && $totalAmount > $request->paid_amount) {
            $supplier = Supplier::findOrFail($request->supplier_id);
            $debt = $totalAmount - $request->paid_amount;
            $supplier->balance += $debt; // رصيده بيزيد (يعني ليه فلوس عندنا)
            $supplier->save();
        }

        return response()->json(['message' => 'تم حفظ فاتورة المشتريات بنجاح']);
    }
}
