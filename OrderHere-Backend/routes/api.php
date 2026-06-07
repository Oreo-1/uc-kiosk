<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FoodController;
use App\Http\Controllers\VendorAuthController;
use App\Http\Controllers\OrderController;

/*
|--------------------------------------------------------------------------
| API Routes - OrderHere Backend
| Base URL: http://103.185.52.14/api
|--------------------------------------------------------------------------
*/

// ═══════════════════════════════════════════════════════════════
// PUBLIC ROUTES (No Auth Required)
// ═══════════════════════════════════════════════════════════════

// ── FOOD ROUTES (Public Read) ─────────────────────────────────
Route::get('/foods', [FoodController::class, 'index'])->name('foods.index');
Route::get('/foods/{food}', [FoodController::class, 'show'])->name('foods.show');
Route::get('/vendors/{vendor_id}/foods', [FoodController::class, 'byVendor'])->name('vendors.foods.index');

// ── ORDER ROUTES (Public) ─────────────────────────────────────
Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
Route::post('/orders', [OrderController::class, 'store'])->name('orders.store');
Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
Route::get('/orders/status/{status}', [OrderController::class, 'byStatus'])->name('orders.byStatus');

// ── VENDOR AUTH (Public) ──────────────────────────────────────
Route::post('/vendor/login', [VendorAuthController::class, 'login'])->name('vendor.login');
Route::post('/vendor/register', [VendorAuthController::class, 'register'])->name('vendor.register');

// ── PAYMENT ROUTES (Public - untuk customer checkout) ─────────
Route::post('/payment/create', [OrderController::class, 'createTransaction'])->name('payment.create');
Route::post('/payment/generate-qris', [OrderController::class, 'generateQRIS'])->name('payment.qris');
Route::post('/payment/notification', [OrderController::class, 'handleNotification'])->name('payment.notification');


// ═══════════════════════════════════════════════════════════════
// PROTECTED ROUTES (Requires Sanctum Token)
// ═══════════════════════════════════════════════════════════════

Route::middleware('auth:sanctum')->group(function () {
    
    // ── VENDOR AUTH (Protected) ───────────────────────────────
    Route::post('/vendor/logout', [VendorAuthController::class, 'logout'])->name('vendor.logout');
    Route::get('/vendor/me', [VendorAuthController::class, 'me'])->name('vendor.me');
    
    // ── FOOD ROUTES (Vendor Only) ─────────────────────────────
    Route::post('/foods', [FoodController::class, 'store'])->name('foods.store');
    Route::put('/foods/{food}', [FoodController::class, 'update'])->name('foods.update');
    Route::post('/foods/{food}', [FoodController::class, 'update']); // Untuk FormData dengan _method: PUT
    Route::patch('/foods/{food}', [FoodController::class, 'update']);
    Route::delete('/foods/{food}', [FoodController::class, 'destroy'])->name('foods.destroy');
    Route::post('/foods/{food}/addons', [FoodController::class, 'addAddon'])->name('foods.addons.store');
    
    // ── ORDER ROUTES (Vendor Only) ────────────────────────────
    Route::get('/vendor/orders', [OrderController::class, 'myOrders'])->name('vendor.orders.my'); // ✅ SINGULAR
    Route::patch('/orders/{order}/status', [OrderController::class, 'updateStatus'])->name('orders.status.update');
    Route::post('/orders/{order}/status', [OrderController::class, 'updateStatus']); // Untuk FormData
});