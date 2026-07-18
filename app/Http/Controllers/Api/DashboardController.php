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
use Carbon\Carbon; // 👈 1. ضفنا مكتبة التعامل مع التواريخ

class DashboardController extends Controller
{
    // 👈 2. ضفنا Request عشان نستقبل التواريخ من المتصفح
    public function index(Request $request)
    {
        try {
            // ==========================================
            // 🌟 تحديد فترة الفلترة (Date Filtering Logic)
            // ==========================================
            $startDate = null;
            $endDate = null;

            // أ. لو العميل باعت فترة جاهزة (اليوم، الأسبوع، الشهر، السنة)
            if ($request->filled('period')) {
                switch ($request->period) {
                    case 'today':
                        $startDate = Carbon::today();
                        $endDate = Carbon::today()->endOfDay();
                        break;
                    case 'week':
                        $startDate = Carbon::now()->startOfWeek();
                        $endDate = Carbon::now()->endOfWeek();
                        break;
                    case 'month':
                        $startDate = Carbon::now()->startOfMonth();
                        $endDate = Carbon::now()->endOfMonth();
                        break;
                    case 'year':
                        $startDate = Carbon::now()->startOfYear();
                        $endDate = Carbon::now()->endOfYear();
                        break;
                }
            }
            // ب. لو العميل باعت تاريخ مخصص من وإلى (From - To)
            elseif ($request->filled('from') && $request->filled('to')) {
                $startDate = Carbon::parse($request->from)->startOfDay();
                $endDate = Carbon::parse($request->to)->endOfDay();
            }

            // ==========================================
            // 1. إجمالي المبيعات والمشتريات (مع فلترة التاريخ لو وجد)
            // ==========================================
            $salesQuery = SaleInvoice::query();
            $purchasesQuery = PurchaseInvoice::query();

            if ($startDate && $endDate) {
                $salesQuery->whereBetween('created_at', [$startDate, $endDate]);
                $purchasesQuery->whereBetween('created_at', [$startDate, $endDate]);
            }

            $totalSales = $salesQuery->sum('total_amount') ?? 0;
            $totalPurchases = $purchasesQuery->sum('total_amount') ?? 0;

            // 2. رصيد الخزنة الحالي (الرصيد التراكمي الفعلي للدرج)
            $cashIn = DrawerTransaction::where('type', 'in')->sum('amount') ?? 0;
            $cashOut = DrawerTransaction::where('type', 'out')->sum('amount') ?? 0;
            $currentDrawer = $cashIn - $cashOut;

            // 3. إجمالي ديون العملاء ومستحقات الموردين (ديون تراكمية)
            $clientsDebt = abs(Client::where('balance', '<', 0)->sum('balance')) ?? 0;
            $suppliersDebt = Supplier::where('balance', '>', 0)->sum('balance') ?? 0;

            // ==========================================
            // 4. 🌟 حساب صافي الربح الحقيقي (مع فلترة الفترة المحددة)
            // ==========================================
            $netProfit = 0;
            $soldItemsQuery = InvoiceItem::with('product');

            // تطبيق فلتر التاريخ على الأصناف المباعة
            if ($startDate && $endDate) {
                $soldItemsQuery->whereBetween('created_at', [$startDate, $endDate]);
            }

            $soldItems = $soldItemsQuery->get();
            foreach ($soldItems as $item) {
                if ($item->product) {
                    // الربح للسطر = (سعر البيع الفعلي بالفاتورة - سعر شراء التكلفة الأصلي بالمخزن) * الكمية المباعة
                    $netProfit += ($item->selling_price - $item->product->purchase_price) * $item->quantity;
                }
            }

            // 5. الأصناف الناقصة
            $lowStockProducts = Product::where('stock_quantity', '<=', 5)->orderBy('stock_quantity', 'asc')->take(5)->get();
            $totalProductsCount = Product::count();
            $lowStockCount = Product::where('stock_quantity', '<=', 5)->count();

            // 6. 🔍 سحب كشوفات الحساب التفصيلية لزرار المودال (مع الفلترة لو وجد تاريخ)
            $salesListQuery = SaleInvoice::with('client')->orderBy('id', 'desc');
            $purchasesListQuery = PurchaseInvoice::with('supplier')->orderBy('id', 'desc');
            $drawerListQuery = DrawerTransaction::orderBy('id', 'desc');

            if ($startDate && $endDate) {
                $salesListQuery->whereBetween('created_at', [$startDate, $endDate]);
                $purchasesListQuery->whereBetween('created_at', [$startDate, $endDate]);
                $drawerListQuery->whereBetween('created_at', [$startDate, $endDate]);
            }

            $salesList = $salesListQuery->take(15)->get()->map(function ($inv) {
                return [
                    'id' => $inv->id,
                    'name' => $inv->client->name ?? 'عميل نقدي',
                    'date' => $inv->created_at->format('Y-m-d h:i A'),
                    'total' => $inv->total_amount,
                    'paid' => $inv->paid_amount
                ];
            });

            $purchasesList = $purchasesListQuery->take(15)->get()->map(function ($inv) {
                return [
                    'id' => $inv->id,
                    'name' => $inv->supplier->name ?? 'مورد نقدي',
                    'date' => $inv->created_at->format('Y-m-d h:i A'),
                    'total' => $inv->total_amount,
                    'paid' => $inv->paid_amount
                ];
            });

            $drawerList = $drawerListQuery->take(15)->get()->map(function ($trans) {
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
                    'net_profit' => $netProfit,
                    'total_products_count' => $totalProductsCount,
                    'low_stock_count' => $lowStockCount,
                    'low_stock_products' => $lowStockProducts,
                    // التواريخ المفلترة حالياً عشان الفرونت إند يعرضها لو حابب
                    'filter' => [
                        'start_date' => $startDate ? $startDate->format('Y-m-d') : null,
                        'end_date' => $endDate ? $endDate->format('Y-m-d') : null,
                    ],
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
