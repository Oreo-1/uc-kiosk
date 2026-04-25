<?php

namespace App\Http\Controllers;

use App\Models\Food;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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
            $query = Food::with('vendor');

            // Optional filters
            if ($request->filled('vendor_id')) {
                $query->where('vendor_id', $request->vendor_id);
            }
            if ($request->filled('type')) {
                $query->where('type', $request->type);
            }
            if ($request->filled('search')) {
                $query->where('name', 'like', '%' . $request->search . '%');
            }
            if ($request->filled('active')) {
                $query->where('active', $request->boolean('active'));
            }

            $perPage = $request->get('per_page', 10);
            $foods = $query->paginate($perPage);

            // ✅ Response langsung dengan data foods (tanpa nesting berlebihan)
            return response()->json([
                'success' => true,
                'message' => 'Foods retrieved successfully.',
                'data' => $foods->items(), // ✅ Langsung array data, bukan object pagination
                'pagination' => [
                    'current_page' => $foods->currentPage(),
                    'per_page' => $foods->perPage(),
                    'total' => $foods->total(),
                    'last_page' => $foods->lastPage(),
                ]
            ], 200);

        } catch (QueryException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Database error occurred.',
                'error' => config('app.debug') ? $e->getMessage() : 'Silakan hubungi administrator.'
            ], 500);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal server error.',
                'error' => config('app.debug') ? $e->getMessage() : 'Terjadi kesalahan tidak terduga.'
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
                'vendor_id'        => ['required', 'integer', 'exists:vendor,id'],
                'name'             => ['required', 'string', 'max:45', 'unique:food,name'],
                'type'             => ['required', Rule::in(['FOOD', 'DRINK', 'SNACK', 'PRASMANAN'])],
                'price'            => ['required', 'numeric', 'min:0'],
                'description'      => ['nullable', 'string', 'max:255'],
                'image'            => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:2048'],
                'estimated_time'   => ['required', 'integer', 'min:1'],
                'flavor_attribute' => ['nullable', Rule::in(['SENANG', 'SEDIH', 'MARAH', 'DATAR'])],
                'active'           => ['nullable', 'boolean'],
            ]);

            // Handle image upload
            if ($request->hasFile('image')) {
                $validated['image'] = $request->file('image')->store('foods', 'public');
            }

            // Convert boolean to tinyint (0/1)
            if (array_key_exists('active', $validated)) {
                $validated['active'] = $validated['active'] ? 1 : 0;
            }

            $food = Food::create($validated);

            // ✅ Tampilkan data yang BARU SAJA masuk + load relasi vendor
            return response()->json([
                'success' => true,
                'message' => 'Food created successfully.',
                'data' => $food->load('vendor') // ✅ Data lengkap dengan relasi
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors()
            ], 422);
        } catch (QueryException $e) {
            // Handle duplicate entry, foreign key constraint, etc.
            if (str_contains($e->getMessage(), 'Duplicate entry')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Food with this name already exists.',
                    'error' => config('app.debug') ? $e->getMessage() : null
                ], 409); // Conflict
            }
            if (str_contains($e->getMessage(), 'foreign key constraint')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid vendor_id. Vendor does not exist.',
                    'error' => config('app.debug') ? $e->getMessage() : null
                ], 400);
            }
            return response()->json([
                'success' => false,
                'message' => 'Database error occurred.',
                'error' => config('app.debug') ? $e->getMessage() : 'Silakan hubungi administrator.'
            ], 500);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal server error.',
                'error' => config('app.debug') ? $e->getMessage() : 'Terjadi kesalahan tidak terduga.'
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
            // ✅ Route model binding sudah handle 404 otomatis, tapi kita tambahkan custom response
            if (!$food->exists) {
                throw new ModelNotFoundException("Food not found");
            }

            return response()->json([
                'success' => true,
                'message' => 'Food retrieved successfully.',
                'data' => $food->load('vendor') // ✅ Langsung tampilkan data food + vendor
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Food not found.',
                'error' => 'The requested food does not exist.'
            ], 404);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal server error.',
                'error' => config('app.debug') ? $e->getMessage() : 'Terjadi kesalahan tidak terduga.'
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

            // ✅ Helper: Ambil nilai hanya jika field benar-benar diisi (bukan empty string)
            $getIfFilled = fn($key) => $request->filled($key) ? $request->input($key) : null;

            // ✅ Validasi: Gunakan nullable + custom rules untuk handle form-data quirks
            $validated = $request->validate([
                'vendor_id'        => ['nullable', 'integer', 'exists:vendor,id'],
                'name'             => ['nullable', 'string', 'max:45', Rule::unique('food', 'name')->ignore($food->id)],
                'type'             => ['nullable', Rule::in(['FOOD', 'DRINK', 'SNACK', 'PRASMANAN'])],
                'price'            => ['nullable', 'numeric', 'min:0'],
                'description'      => ['nullable', 'string', 'max:255'],
                'image'            => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:2048'],
                'estimated_time'   => ['nullable', 'integer', 'min:1'],
                'flavor_attribute' => ['nullable', Rule::in(['SENANG', 'SEDIH', 'MARAH', 'DATAR'])],
                'active'           => ['nullable', 'boolean'], // ✅ Laravel handle "1","0",true,false
            ]);

            // ✅ Filter: Hanya ambil field yang benar-benar diisi (hindari empty string overwrite)
            $updates = array_filter($validated, fn($v) => $v !== null && $v !== '');

            // ✅ Handle image upload (form-data only)
            if ($request->hasFile('image')) {
                if ($food->image && Storage::disk('public')->exists($food->image)) {
                    Storage::disk('public')->delete($food->image);
                }
                $updates['image'] = $request->file('image')->store('foods', 'public');
            }

            // ✅ Handle boolean 'active' untuk form-data (convert string "true"/"false")
            if (array_key_exists('active', $updates)) {
                $val = $updates['active'];
                $updates['active'] = match (strtolower($val)) {
                    'true', '1', 1 => true,
                    'false', '0', 0 => false,
                    default => (bool) $val,
                };
            }

            // ✅ Jika tidak ada field yang diupdate, return success tanpa perubahan
            if (empty($updates)) {
                return response()->json([
                    'success' => true,
                    'message' => 'No changes to update.',
                    'data' => $food->fresh()->load('vendor')
                ], 200);
            }

            // ✅ Eksekusi update
            $food->update($updates);

            return response()->json([
                'success' => true,
                'message' => 'Food updated successfully.',
                'data' => $food->fresh()->load('vendor')
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Validation failed.', 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            \Log::error('Food update failed:', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Update failed.', 'error' => config('app.debug') ? $e->getMessage() : 'Internal server error.'], 500);
        }
    }

    /**
     * Remove the specified food.
     * DELETE /api/foods/{food}
     */
    public function destroy(Food $food)
    {
        try {
            // ✅ Cek 404 manual
            if (!$food->exists) {
                throw new ModelNotFoundException("Food not found");
            }

            // Clean up associated image
            if ($food->image && Storage::disk('public')->exists($food->image)) {
                Storage::disk('public')->delete($food->image);
            }

            // Simpan data sebelum delete untuk ditampilkan di response
            $deletedFood = $food->load('vendor');
            
            $food->delete();

            // ✅ Tampilkan data yang BARU SAJA dihapus (sebagai konfirmasi)
            return response()->json([
                'success' => true,
                'message' => 'Food deleted successfully.',
                'data' => $deletedFood // ✅ Data food yang dihapus
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
                    'error' => config('app.debug') ? $e->getMessage() : null
                ], 409);
            }
            return response()->json([
                'success' => false,
                'message' => 'Database error occurred.',
                'error' => config('app.debug') ? $e->getMessage() : 'Silakan hubungi administrator.'
            ], 500);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal server error.',
                'error' => config('app.debug') ? $e->getMessage() : 'Terjadi kesalahan tidak terduga.'
            ], 500);
        }
    }
}