<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\SaleInvoiceController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\ReturnController;

// مسار المرتجعات
Route::post('/returns', [ReturnController::class, 'store']);

// مسارات الموردين
Route::get('/suppliers', [SupplierController::class, 'index']);
Route::post('/suppliers', [SupplierController::class, 'store']);
Route::post('/suppliers/{id}/pay', [SupplierController::class, 'pay']);

// مسارات العملاء
Route::get('/clients', [ClientController::class, 'index']);
Route::post('/clients', [ClientController::class, 'store']);
Route::post('/clients/{id}/pay', [ClientController::class, 'pay']); // مسار تسديد الدفعة

// مسار إنشاء فاتورة مبيعات
Route::post('/invoices', [SaleInvoiceController::class, 'store']);
// مسارات التصنيفات
Route::get('/categories', [CategoryController::class, 'index']);
Route::post('/categories', [CategoryController::class, 'store']);

// مسارات الأصناف (قطع الغيار)
Route::get('/products', [ProductController::class, 'index']);
Route::post('/products', [ProductController::class, 'store']);
