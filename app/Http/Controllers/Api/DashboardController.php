<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SaleInvoice;
use App\Models\PurchaseInvoice;
use App\Models\Client;
use App\Models\Supplier;
use App\Models\Product;
use App\Models\InvoiceItem;
use App\Models\DrawerTransaction;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        try {
            // 1. إجمالي المبيعات والمشتريات
            $totalSales = SaleInvoice::sum('total_amount') ?? 0;
            $totalPurchases = PurchaseInvoice::sum('total_amount') ?? 0;

            // 2. رصيد الخزنة الحالي (الوارد - الصادر)
            $cashIn = DrawerTransaction::where('type', 'in')->sum('amount') ?? 0;
            $cashOut = DrawerTransaction::where('type', 'out')->sum('amount') ?? 0;
            $currentDrawer = $cashIn - $cashOut;

            // 3. إجمالي ديون العملاء ومستحقات الموردين
            $clientsDebt = abs(Client::where('balance', '<', 0)->sum('balance')) ?? 0;
            $suppliersDebt = Supplier::where('balance', '>', 0)->sum('balance') ?? 0;

            // 4. 🌟 حساب صافي الربح الحقيقي من واقع المبيعات (سعر البيع - سعر الشراء والتكلفة)
            $netProfit = 0;
            $soldItems = InvoiceItem::with('product')->get();
            foreach ($soldItems as $item) {
                if ($item->product) {
                    // الربح للسطر = (سعر البيع الفعلي بالفاتورة - سعر شراء التكلفة الأصلي بالمخزن) * الكمية المتباعة
                    $netProfit += ($item->selling_price - $item->product->purchase_price) * $item->quantity;
                }
            }

            // 5. الأصناف الناقصة
            $lowStockProducts = Product::where('stock_quantity', '<=', 5)->orderBy('stock_quantity', 'asc')->take(5)->get();
            $totalProductsCount = Product::count();
            $lowStockCount = Product::where('stock_quantity', '<=', 5)->count();

            // 6. 🔍 سحب كشوفات الحساب التفصيلية لزرار المودال (Drill-down Lists)
            $salesList = SaleInvoice::with('client')->orderBy('id', 'desc')->take(15)->get()->map(function ($inv) {
                return [
                    'id' => $inv->id,
                    'name' => $inv->client->name ?? 'عميل نقدي',
                    'date' => $inv->created_at->format('Y-m-d h:i A'),
                    'total' => $inv->total_amount,
                    'paid' => $inv->paid_amount
                ];
            });

            $purchasesList = PurchaseInvoice::with('supplier')->orderBy('id', 'desc')->take(15)->get()->map(function ($inv) {
                return [
                    'id' => $inv->id,
                    'name' => $inv->supplier->name ?? 'مورد نقدي',
                    'date' => $inv->created_at->format('Y-m-d h:i A'),
                    'total' => $inv->total_amount,
                    'paid' => $inv->paid_amount
                ];
            });

            $drawerList = DrawerTransaction::orderBy('id', 'desc')->take(15)->get()->map(function ($trans) {
                return [
                    'date' => $trans->created_at->format('Y-m-d h:i A'),
                    'type' => $trans->type == 'in' ? 'وارد للدرج' : ($trans->type == 'out' ? 'صادر / مصروف' : 'تسوية حساب'),
                    'amount' => $trans->amount,
                    'desc' => $trans->description
                ];
            });

            $clientsList = Client::where('balance', '<', 0)->orderBy('balance', 'asc')->get()->map(function ($c) {
                return [
                    'name' => $c->name,
                    'phone' => $c->phone ?? '---',
                    'amount' => abs($c->balance)
                ];
            });

            $suppliersList = Supplier::where('balance', '>', 0)->orderBy('balance', 'desc')->get()->map(function ($s) {
                return [
                    'name' => $s->name,
                    'phone' => $s->phone ?? '---',
                    'amount' => $s->balance
                ];
            });

            return response()->json([
                'status' => 'success',
                'data' => [
                    'total_sales' => $totalSales,
                    'total_purchases' => $totalPurchases,
                    'current_drawer' => $currentDrawer,
                    'clients_debt' => $clientsDebt,
                    'suppliers_debt' => $suppliersDebt,
                    'net_profit' => $netProfit, // الكارت الجديد
                    'total_products_count' => $totalProductsCount,
                    'low_stock_count' => $lowStockCount,
                    'low_stock_products' => $lowStockProducts,
                    // قوائم التفاصيل
                    'lists' => [
                        'sales' => $salesList,
                        'purchases' => $purchasesList,
                        'drawer' => $drawerList,
                        'clients' => $clientsList,
                        'suppliers' => $suppliersList
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }
}
