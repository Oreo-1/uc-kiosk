<?php

namespace App\Http\Controllers;

use App\Models\Food;
use App\Models\FoodAddon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log; // ✅ FIX 1: Tambahkan import Log
use Throwable;

class FoodController extends Controller
{
    /**
     * Display a listing of foods.
     * GET /api/foods
     */
    public function index(Request $request)
    {
        try {
            $query = Food::with(['vendor', 'addons.addon']);

            if ($request->filled('vendor_id')) {
                $query->where('vendor_id', $request->input('vendor_id')); // ✅ FIX 2: get() -> input()
            }
            if ($request->filled('type')) {
                $query->where('type', $request->input('type'));
            }
            if ($request->filled('search')) {
                $query->where('name', 'like', '%' . $request->input('search') . '%');
            }
            if ($request->filled('active')) {
                $query->where('active', $request->boolean('active'));
            }

            $perPage = $request->input('per_page', 50);
            $foods = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Foods retrieved successfully.',
                'data' => $foods->items(),
                'pagination' => [
                    'current_page' => $foods->currentPage(),
                    'per_page' => $foods->perPage(),
                    'total' => $foods->total(),
                    'last_page' => $foods->lastPage(),
                ]
            ], 200);

        } catch (QueryException $e) {
            Log::error('Food index failed:', ['error' => $e->getMessage()]); // ✅ FIX 3: Gunakan Log::
            return response()->json([
                'success' => false,
                'message' => 'Database error occurred.',
                'error' => config('app.debug') === true ? $e->getMessage() : 'Silakan hubungi administrator.'
            ], 500);
        } catch (Throwable $e) {
            Log::error('Food index failed:', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Internal server error.',
                'error' => config('app.debug') === true ? $e->getMessage() : 'Terjadi kesalahan tidak terduga.'
            ], 500);
        }
    }

    /**
     * Display a listing of foods for a specific vendor.
     * GET /api/vendors/{vendor_id}/foods
     */
    public function byVendor(Request $request, $vendorId)
    {
        try {
            if (!\App\Models\Vendor::where('id', $vendorId)->exists()) {
                 return response()->json([
                    'success' => false,
                    'message' => 'Vendor not found.',
                ], 404);
            }

            $query = Food::where('vendor_id', $vendorId)->with(['vendor', 'addons.addon']);

            if ($request->filled('type')) {
                $query->where('type', $request->input('type'));
            }
            if ($request->filled('search')) {
                $query->where('name', 'like', '%' . $request->input('search') . '%');
            }
            if ($request->filled('active')) {
                $query->where('active', $request->boolean('active'));
            }

            $perPage = $request->input('per_page', 50);
            $foods = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Vendor foods retrieved successfully.',
                'data' => $foods->items(),
                'pagination' => [
                    'current_page' => $foods->currentPage(),
                    'per_page' => $foods->perPage(),
                    'total' => $foods->total(),
                    'last_page' => $foods->lastPage(),
                ]
            ], 200);

        } catch (QueryException $e) {
            Log::error('Food byVendor failed:', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Database error occurred.',
                'error' => config('app.debug') === true ? $e->getMessage() : 'Silakan hubungi administrator.'
            ], 500);
        } catch (Throwable $e) {
            Log::error('Food byVendor failed:', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Internal server error.',
                'error' => config('app.debug') === true ? $e->getMessage() : 'Terjadi kesalahan tidak terduga.'
            ], 500);
        }
    }

    /**
     * Store a newly created food.
     * POST /api/foods
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name'             => ['required', 'string', 'max:45', 'unique:food,name'],
                'type'             => ['required', Rule::in(['FOOD', 'DRINK', 'SNACK', 'PRASMANAN'])],
                'price'            => ['required', 'numeric', 'min:0'],
                'description'      => ['nullable', 'string', 'max:255'],
                'image'            => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:2048'],
                'estimated_time'   => ['required', 'integer', 'min:1'],
                'flavor_attribute' => ['nullable', Rule::in(['SENANG', 'SEDIH', 'MARAH', 'DATAR'])],
                'active'           => ['nullable', 'boolean'],
            ]);

            if ($request->hasFile('image')) {
                $validated['image'] = $request->file('image')->store('foods', 'public');
            }

            $validated['vendor_id'] = $request->user()->id;

            $food = Food::create($validated);

            return response()->json([
                'success' => true,
                'message' => 'Food created successfully.',
                'data' => $food->load('vendor')
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors()
            ], 422);
        } catch (Throwable $e) {
            Log::error('Food store failed:', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Create failed.',
                'error' => config('app.debug') === true ? $e->getMessage() : 'Internal server error.'
            ], 500);
        }
    }

    /**
     * Display the specified food.
     * GET /api/foods/{food}
     */
    public function show(Food $food)
    {
        try {
            if (!$food->exists) {
                throw new ModelNotFoundException("Food not found");
            }

            return response()->json([
                'success' => true,
                'message' => 'Food retrieved successfully.',
                'data' => $food->load(['vendor', 'addons'])
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Food not found.',
                'error' => 'The requested food does not exist.'
            ], 404);
        } catch (Throwable $e) {
            Log::error('Food show failed:', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Internal server error.',
                'error' => config('app.debug') === true ? $e->getMessage() : 'Terjadi kesalahan tidak terduga.'
            ], 500);
        }
    }

    /**
     * Update the specified food.
     * PUT/PATCH /api/foods/{food}
     */
    public function update(Request $request, Food $food)
    {
        try {
            if (!$food->exists) {
                return response()->json(['success' => false, 'message' => 'Food not found.'], 404);
            }

            $authenticatedVendor = $request->user();
            if (!$authenticatedVendor || $food->vendor_id !== $authenticatedVendor->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Forbidden.',
                    'error' => 'You can only update your own foods.'
                ], 403);
            }

            $validated = $request->validate([
                'name'             => ['nullable', 'string', 'max:45', Rule::unique('food', 'name')->ignore($food->id)],
                'type'             => ['nullable', Rule::in(['FOOD', 'DRINK', 'SNACK', 'PRASMANAN'])],
                'price'            => ['nullable', 'numeric', 'min:0'],
                'description'      => ['nullable', 'string', 'max:255'],
                'image'            => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:2048'],
                'estimated_time'   => ['nullable', 'integer', 'min:1'],
                'flavor_attribute' => ['nullable', Rule::in(['SENANG', 'SEDIH', 'MARAH', 'DATAR'])],
                'active'           => ['nullable', 'boolean'],
            ]);

            $updates = array_filter($validated, fn($v) => $v !== null && $v !== '');

            if ($request->hasFile('image')) {
                if ($food->image && Storage::disk('public')->exists($food->image)) {
                    Storage::disk('public')->delete($food->image);
                }
                $updates['image'] = $request->file('image')->store('foods', 'public');
            }

            if (array_key_exists('active', $updates)) {
                $val = $updates['active'];
                $updates['active'] = match (strtolower(strval($val))) {
                    'true', '1', 1 => true,
                    'false', '0', 0 => false,
                    default => filter_var($val, FILTER_VALIDATE_BOOLEAN),
                };
            }

            if (empty($updates)) {
                return response()->json([
                    'success' => true,
                    'message' => 'No changes to update.',
                    'data' => $food->fresh()->load('vendor')
                ], 200);
            }

            $food->update($updates);

            return response()->json([
                'success' => true,
                'message' => 'Food updated successfully.',
                'data' => $food->fresh()->load('vendor')
            ], 200);

        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Validation failed.', 'errors' => $e->errors()], 422);
        } catch (Throwable $e) {
            Log::error('Food update failed:', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Update failed.', 'error' => config('app.debug') === true ? $e->getMessage() : 'Internal server error.'], 500);
        }
    }

    /**
     * Remove the specified food.
     * DELETE /api/foods/{food}
     */
    public function destroy(Food $food)
    {
        try {
            if (!$food->exists) {
                throw new ModelNotFoundException("Food not found");
            }

            $authenticatedVendor = request()->user();
            if (!$authenticatedVendor || $food->vendor_id !== $authenticatedVendor->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Forbidden.',
                    'error' => 'You can only delete your own foods.'
                ], 403);
            }

            if ($food->image && Storage::disk('public')->exists($food->image)) {
                Storage::disk('public')->delete($food->image);
            }

            $deletedFood = $food->load('vendor');
            $food->delete();

            return response()->json([
                'success' => true,
                'message' => 'Food deleted successfully.',
                'data' => $deletedFood
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Food not found.',
                'error' => 'The requested food does not exist.'
            ], 404);
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'foreign key constraint')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete food. It is still referenced in orders.',
                    'error' => config('app.debug') === true ? $e->getMessage() : null
                ], 409);
            }
            Log::error('Food destroy failed:', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Database error occurred.',
                'error' => config('app.debug') === true ? $e->getMessage() : 'Silakan hubungi administrator.'
            ], 500);
        } catch (Throwable $e) {
            Log::error('Food destroy failed:', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Internal server error.',
                'error' => config('app.debug') === true ? $e->getMessage() : 'Terjadi kesalahan tidak terduga.'
            ], 500);
        }
    }

    /**
     * Add an addon to a specific food.
     * POST /api/foods/{food}/addons
     */
    public function addAddon(Request $request, Food $food)
    {
        try {
            if (!$food->exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Food not found.'
                ], 404);
            }

            $authenticatedVendor = $request->user();
            if (!$authenticatedVendor || $food->vendor_id !== $authenticatedVendor->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Forbidden.',
                    'error'   => 'You can only manage addons for your own foods.'
                ], 403);
            }

            $validated = $request->validate([
                'addons_id'   => [
                    'required',
                    'integer',
                    'exists:food,id',
                    function ($attribute, $value, $fail) use ($food) {
                        if ((int) $value === $food->id) {
                            $fail('A food cannot be its own addon.');
                        }
                    },
                ],
                'extra_price' => ['nullable', 'numeric', 'min:0'],
            ]);

            $exists = FoodAddon::where('food_id', $food->id)
                               ->where('addons_id', $validated['addons_id'])
                               ->exists();

            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Addon already added to this food.'
                ], 409);
            }

            $addon = FoodAddon::create([
                'food_id'     => $food->id,
                'addons_id'   => $validated['addons_id'],
                'extra_price' => $validated['extra_price'] ?? null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Addon added successfully.',
                'data'    => $addon->load('addon')
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => $e->errors()
            ], 422);
        } catch (Throwable $e) {
            Log::error('Food addAddon failed:', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to add addon.',
                'error'   => config('app.debug') === true ? $e->getMessage() : 'Internal server error.'
            ], 500);
        }
    }
}