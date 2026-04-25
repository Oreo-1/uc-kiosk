<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FoodController;

// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');

   // ================= FOOD ROUTES =================
    // GET    /api/foods           -> index (list with filters)
    // POST   /api/foods           -> store (create new)
    // GET    /api/foods/{food}    -> show (detail)
    // PUT    /api/foods/{food}    -> update
    // DELETE /api/foods/{food}    -> destroy

// ================= FOOD ROUTES (Explicit Definition) =================

// GET /api/foods - List all foods with optional filters
Route::get('/foods', [FoodController::class, 'index'])->name('foods.index');

// POST /api/foods - Create new food
Route::post('/foods', [FoodController::class, 'store'])->name('foods.store');

// GET /api/foods/{food} - Show single food detail
// ⚠️ Route model binding: Laravel will auto-find Food by {food} = id
Route::get('/foods/{food}', [FoodController::class, 'show'])->name('foods.show');

// PUT/PATCH /api/foods/{food} - Update existing food
Route::put('/foods/{food}', [FoodController::class, 'update'])->name('foods.update');
Route::patch('/foods/{food}', [FoodController::class, 'update']); // alias

// DELETE /api/foods/{food} - Delete food
Route::delete('/foods/{food}', [FoodController::class, 'destroy'])->name('foods.destroy');