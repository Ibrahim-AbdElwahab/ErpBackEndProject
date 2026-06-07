<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SaleInvoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\Client;
use App\Models\DrawerTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SaleInvoiceController extends Controller
{
    // 1. عرض الفواتير
    public function index()
    {
        $invoices = SaleInvoice::with('items.product', 'client')->orderBy('id', 'desc')->get();
        return response()->json([
            'status' => 'success',
            'data' => $invoices
        ]);
    }

    // 2. حفظ الفاتورة
    public function store(Request $request)
    {
        $request->validate([
            'paid_amount' => 'required|numeric|min:0',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric|min:0',
        ]);

        try {
            DB::beginTransaction();

            $totalAmount = 0;
            foreach ($request->items as $item) {
                $totalAmount += ($item['price'] * $item['quantity']);
            }

            // أ. إنشاء رأس الفاتورة
            $invoice = SaleInvoice::create([
                'client_id' => $request->client_id,
                'total_amount' => $totalAmount,
                'paid_amount' => $request->paid_amount,
                'user_id' => 1,
            ]);

            // ب. إضافة الأصناف
            foreach ($request->items as $item) {
                InvoiceItem::create([
                    'sale_invoice_id' => $invoice->id,
                    'product_id'      => $item['product_id'],
                    'quantity'        => $item['quantity'],
                    'selling_price'   => $item['price'], // بنحفظ السعر باسم selling_price
                    'subtotal'        => $item['price'] * $item['quantity'],
                ]);

                // خصم من المخزن
                $product = Product::findOrFail($item['product_id']);
                $product->stock_quantity -= $item['quantity'];
                $product->save();
            }

            // ج. تحديث حساب العميل
            if ($request->client_id) {
                $remaining = $totalAmount - $request->paid_amount;
                if ($remaining > 0) {
                    $client = Client::findOrFail($request->client_id);
                    $client->balance -= $remaining;
                    $client->save();
                }
            }

            // د. إيداع الخزنة
            if ($request->paid_amount > 0) {
                DrawerTransaction::create([
                    'user_id' => 1,
                    'type' => 'in',
                    'amount' => $request->paid_amount,
                    'description' => "إيراد مبيعات نقدية - فاتورة رقم #" . $invoice->id,
                ]);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'تم حفظ الفاتورة بنجاح',
                'data' => $invoice
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // دالة جلب تفاصيل الفاتورة بالكامل (للمودال)
    public function showInvoice($id)
    {
        $sale = \App\Models\SaleInvoice::with('items.product')->findOrFail($id);

        $formattedItems = $sale->items->map(function ($item) {
            return [
                'id' => $item->id,
                'name' => $item->product ? $item->product->name : 'صنف غير متوفر',
                'quantity' => $item->quantity,
                // التعديل هنا: نقرأ السعر اللي اتسجل في الداتا بيز (selling_price)
                'price' => $item->selling_price ?? 0,
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => [
                'id' => $sale->id,
                'invoice_number' => $sale->id,
                'date' => $sale->created_at->format('Y-m-d h:i A'),
                'type' => 'فاتورة مبيعات',
                'total_amount' => $sale->total_amount,
                'items' => $formattedItems
            ]
        ]);
    }
}
