<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\ReturnController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\SaleInvoiceController;
use App\Http\Controllers\Api\PurchaseInvoiceController; // 👈 استدعاء كنترولر المشتريات

// 🔓 مسار مفتوح للكل (عشان نقدر نسجل دخول)
Route::post('/login', [AuthController::class, 'login']);

// 🔒 مسارات محمية (لازم توكن)
Route::get('/dashboard', [DashboardController::class, 'index']);

// المخزن
Route::get('/products', [ProductController::class, 'index']);
Route::post('/products', [ProductController::class, 'store']);

// الموردين
Route::get('/suppliers', [SupplierController::class, 'index']);
Route::post('/suppliers', [SupplierController::class, 'store']);
Route::post('/suppliers/{id}/pay', [SupplierController::class, 'pay']);

// المرتجعات
Route::post('/returns', [ReturnController::class, 'store']);

// العملاء
Route::get('/clients', [ClientController::class, 'index']);
Route::post('/clients', [ClientController::class, 'store']);
Route::post('/clients/{id}/pay', [ClientController::class, 'pay']);

// المبيعات
Route::post('/invoices', [SaleInvoiceController::class, 'store']);

// المشتريات 👈 (المسار اللي كان ناقص)
Route::post('/purchase-invoices', [PurchaseInvoiceController::class, 'store']);

// كشف حساب العميل
Route::get('/clients/{id}/statement', [ClientController::class, 'statement']);

// كشف حساب المورد
Route::get('/suppliers/{id}/statement', [SupplierController::class, 'statement']);

Route::put('/clients/{id}', [\App\Http\Controllers\Api\ClientController::class, 'update']);

Route::put('/suppliers/{id}', [\App\Http\Controllers\Api\SupplierController::class, 'update']);

Route::get('/categories', [\App\Http\Controllers\Api\CategoryController::class, 'index']);
Route::post('/categories', [\App\Http\Controllers\Api\CategoryController::class, 'store']);
Route::put('/categories/{id}', [\App\Http\Controllers\Api\CategoryController::class, 'update']);

Route::put('/products/{id}', [\App\Http\Controllers\Api\ProductController::class, 'update']);

Route::post('/sales', [\App\Http\Controllers\Api\SaleInvoiceController::class, 'store']);
Route::get('/sales', [\App\Http\Controllers\Api\SaleInvoiceController::class, 'index']);

Route::get('/dashboard/stats', [\App\Http\Controllers\Api\DashboardController::class, 'index']);

// ضيف السطر ده مع باقي الروابط بتاعتك
Route::get('/invoices/{id}', [\App\Http\Controllers\Api\SaleInvoiceController::class, 'showInvoice']);

// متنساش تعمل use للكنترولر فوق

// الراوت الجديد للمرتجعات
Route::get('/returns/{id}', [ReturnController::class, 'showReturn']);

Route::get('/purchases/{id}', [\App\Http\Controllers\Api\PurchaseInvoiceController::class, 'showPurchase']);
