<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FoodController;
use App\Http\Controllers\VendorAuthController;
use App\Http\Controllers\OrderController;

/*
|--------------------------------------------------------------------------
| API Routes - OrderHere Backend
| Base URL: http://localhost/api
|--------------------------------------------------------------------------
*/

// ================= PUBLIC ROUTES (No Auth Required) =================
    // GET /api/foods - List all foods with optional filters
    Route::get('/foods', [FoodController::class, 'index'])->name('foods.index');
        // Route::get('/foods/{food}', [FoodController::class, 'show'])->name('foods.show')->middleware('auth:sanctum');
    Route::get('/foods/{food}', [FoodController::class, 'show'])->name('foods.show');

        // ================= OPTIONAL: Vendor-Specific Food Routes =================
    // Get foods belonging to authenticated vendor only
    Route::get('/vendor/foods', function (Request $request) {
        return response()->json(
            $request->user()->foods()->paginate($request->get('per_page', 10))
        );
    })->name('vendor.foods.index');

    // List & Create Orders
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::post('/orders', [OrderController::class, 'store'])->name('orders.store');

    // View Single Order
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');

    // Filter Orders
    Route::get('/vendors/{vendor_id}/orders', [OrderController::class, 'byVendor'])->name('orders.byVendor');
    Route::get('/orders/status/{status}', [OrderController::class, 'byStatus'])->name('orders.byStatus');


    // Vendor Authentication
    Route::post('/vendor/login', [VendorAuthController::class, 'login'])->name('vendor.login');
    Route::post('/vendor/register', [VendorAuthController::class, 'register'])->name('vendor.register'); // ⭐ Baru

// Note: Logout requires auth, so it's in protected routes below

// ================= PROTECTED ROUTES (Requires Sanctum Token) =================

Route::middleware('auth:sanctum')->group(function () {
    
    // Vendor Logout
    Route::post('/vendor/logout', [VendorAuthController::class, 'logout'])->name('vendor.logout');

    // ================= FOOD ROUTES =================
    // All food operations require authenticated vendor

    // POST /api/foods - Create new food (vendor only)
    Route::post('/foods', [FoodController::class, 'store'])->name('foods.store');

    // GET /api/foods/{food} - Show single food detail (public read allowed)
    // Uncomment middleware below if you want to protect read access too:

    // PUT/PATCH /api/foods/{food} - Update existing food (vendor only)
    Route::put('/foods/{food}', [FoodController::class, 'update'])->name('foods.update');
    Route::patch('/foods/{food}', [FoodController::class, 'update']); // alias

    // DELETE /api/foods/{food} - Delete food (vendor only)
    Route::delete('/foods/{food}', [FoodController::class, 'destroy'])->name('foods.destroy');

    Route::patch('/orders/{order}/status', [OrderController::class, 'updateStatus'])->name('orders.status.update');

});
