<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Food;
use App\Models\OrderFood;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    /**
     * Display a listing of orders (PUBLIC).
     * GET /api/orders
     */
    public function index(Request $request)
    {
        try {
            $query = Order::with(['vendor', 'foods']);

            // Optional filters
            if ($request->filled('vendor_id')) {
                $query->where('vendor_id', $request->vendor_id);
            }
            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }
            if ($request->filled('queue_number')) {
                $query->where('queue_number', $request->queue_number);
            }
            if ($request->filled('min_price')) {
                $query->where('total_price', '>=', $request->min_price);
            }
            if ($request->filled('max_price')) {
                $query->where('total_price', '<=', $request->max_price);
            }

            $perPage = $request->get('per_page', 10);
            $orders = $query->orderBy('id', 'desc')->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Orders retrieved successfully.',
                'data' => $orders->items(),
                'pagination' => [
                    'current_page' => $orders->currentPage(),
                    'per_page' => $orders->perPage(),
                    'total' => $orders->total(),
                    'last_page' => $orders->lastPage(),
                ]
            ], 200);

        } catch (QueryException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Database error occurred.',
                'error' => config('app.debug') === true ? $e->getMessage() : 'Silakan hubungi administrator.'
            ], 500);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal server error.',
                'error' => config('app.debug') === true ? $e->getMessage() : 'Terjadi kesalahan tidak terduga.'
            ], 500);
        }
    }

    /**
     * Store a newly created order from cart data (PUBLIC).
     * POST /api/orders
     */
    /**
     * Store a newly created order from cart data (PUBLIC).
     * POST /api/orders
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                // ❌ HAPUS vendor_id & queue_number dari input client
                'foods'        => ['required', 'array', 'min:1'], // Cart items from local storage
                'foods.*.food_id'  => ['required', 'integer', 'exists:food,id'],
                'foods.*.quantity' => ['required', 'integer', 'min:1'],
                'foods.*.notes'    => ['nullable', 'string', 'max:255'],
            ]);

            // ✅ STEP 1: Ambil vendor_id dari food pertama
            $firstFood = Food::findOrFail($validated['foods'][0]['food_id']);
            $vendorId = $firstFood->vendor_id;

            // ✅ STEP 2: Validasi semua food harus dari vendor yang sama
            $foodIds = array_column($validated['foods'], 'food_id');
            $foods = Food::whereIn('id', $foodIds)->get();
            
            if ($foods->pluck('vendor_id')->unique()->count() > 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid cart.',
                    'error' => 'All items in an order must belong to the same vendor.'
                ], 400);
            }

            // ✅ STEP 3: Generate queue_number otomatis (reset per hari per vendor)
            $today = now()->startOfDay();
            $todayOrderCount = Order::where('vendor_id', $vendorId)
                ->whereDate('created_at', $today) // ⚠️ Jika tidak ada created_at, gunakan updated_at atau tambahkan kolom order_date
                ->count();
            $queueNumber = $todayOrderCount + 1;

            // ✅ STEP 4: Hitung totals & prepare order foods
            $totalPrice = 0;
            $totalEstimated = 0;
            $orderFoods = [];

            foreach ($validated['foods'] as $item) {
                $food = $foods->firstWhere('id', $item['food_id']);
                
                if (!$food->active) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Food not available.',
                        'error' => "Food '{$food->name}' is currently inactive."
                    ], 400);
                }

                $itemTotal = $food->price * $item['quantity'];
                $totalPrice += $itemTotal;
                $totalEstimated += $food->estimated_time * $item['quantity'];

                $orderFoods[] = [
                    'food_id'     => $food->id,
                    'quantity'    => $item['quantity'],
                    'total_price' => $itemTotal,
                    'notes'       => $item['notes'] ?? null,
                ];
            }

            // ✅ STEP 5: Transaction untuk atomic insert
            $order = DB::transaction(function () use ($vendorId, $queueNumber, $totalPrice, $totalEstimated, $orderFoods) {
                $order = Order::create([
                    'vendor_id'      => $vendorId,
                    'status'         => 'ONPROGRESS', // Default status
                    'queue_number'   => $queueNumber,
                    'total_price'    => $totalPrice,
                    'total_estimated'=> $totalEstimated,
                ]);

                foreach ($orderFoods as $foodItem) {
                    $order->foods()->attach($foodItem['food_id'], [
                        'quantity'    => $foodItem['quantity'],
                        'total_price' => $foodItem['total_price'],
                        'notes'       => $foodItem['notes'],
                    ]);
                }

                return $order;
            });

            return response()->json([
                'success' => true,
                'message' => 'Order placed successfully.',
                'data' => $order->load(['vendor', 'foods'])
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors()
            ], 422);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Food not found.',
                'error' => 'One or more food items do not exist.'
            ], 404);
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'foreign key constraint')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid reference.',
                    'error' => 'Vendor or Food ID does not exist.'
                ], 400);
            }
            return response()->json([
                'success' => false,
                'message' => 'Database error occurred.',
                'error' => config('app.debug') === true ? $e->getMessage() : 'Silakan hubungi administrator.'
            ], 500);
        } catch (Throwable $e) {
            \Log::error('Order store failed:', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to place order.',
                'error' => config('app.debug') === true ? $e->getMessage() : 'Internal server error.'
            ], 500);
        }
    }

    
    /**
     * Display the specified order (PUBLIC).
     * GET /api/orders/{order}
     */
    public function show(Order $order)
    {
        try {
            if (!$order->exists) {
                throw new ModelNotFoundException("Order not found");
            }

            return response()->json([
                'success' => true,
                'message' => 'Order retrieved successfully.',
                'data' => $order->load(['vendor', 'foods'])
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found.',
                'error' => 'The requested order does not exist.'
            ], 404);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal server error.',
                'error' => config('app.debug') === true ? $e->getMessage() : 'Terjadi kesalahan tidak terduga.'
            ], 500);
        }
    }

    /**
     * Update order status (VENDOR ONLY - Auth Required).
     * PATCH /api/orders/{order}/status
     */
    public function updateStatus(Request $request, Order $order)
    {
        try {
            if (!$order->exists) {
                throw new ModelNotFoundException("Order not found");
            }

            // 🔐 Authorization: Only the vendor who owns this order can update status
            $authenticatedVendor = $request->user();
            if (!$authenticatedVendor || $order->vendor_id !== $authenticatedVendor->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Forbidden.',
                    'error' => 'You can only update status of your own orders.'
                ], 403);
            }

            $validated = $request->validate([
                'status' => ['required', Rule::in(['DONE', 'ONPROGRESS'])],
            ]);

            $order->update(['status' => $validated['status']]);

            return response()->json([
                'success' => true,
                'message' => 'Order status updated successfully.',
                'data' => $order->fresh()->load(['vendor', 'foods'])
            ], 200);

        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Validation failed.', 'errors' => $e->errors()], 422);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found.',
                'error' => 'The requested order does not exist.'
            ], 404);
        } catch (Throwable $e) {
            \Log::error('Update order status failed:', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Update failed.', 'error' => config('app.debug') === true ? $e->getMessage() : 'Internal server error.'], 500);
        }
    }

    /**
     * Get orders by vendor (PUBLIC - for tracking).
     * GET /api/vendors/{vendor_id}/orders
     */
    public function byVendor(Request $request, $vendor_id)
    {
        try {
            $query = Order::with('foods')
                ->where('vendor_id', $vendor_id)
                ->orderBy('id', 'desc');

            // Optional status filter
            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            $perPage = $request->get('per_page', 10);
            $orders = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Orders retrieved successfully.',
                'data' => $orders->items(),
                'pagination' => [
                    'current_page' => $orders->currentPage(),
                    'per_page' => $orders->perPage(),
                    'total' => $orders->total(),
                    'last_page' => $orders->lastPage(),
                ]
            ], 200);

        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve orders.',
                'error' => config('app.debug') === true ? $e->getMessage() : 'Internal server error.'
            ], 500);
        }
    }

    /**
     * Get orders by status (PUBLIC - for customer tracking).
     * GET /api/orders/status/{status}
     */
    public function byStatus(Request $request, $status)
    {
        try {
            if (!in_array($status, ['DONE', 'ONPROGRESS'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid status.',
                    'error' => 'Status must be DONE or ONPROGRESS.'
                ], 400);
            }

            $query = Order::with(['vendor', 'foods'])
                ->where('status', $status)
                ->orderBy('id', 'desc');

            // Optional vendor filter
            if ($request->filled('vendor_id')) {
                $query->where('vendor_id', $request->vendor_id);
            }

            $perPage = $request->get('per_page', 10);
            $orders = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => "Orders with status '{$status}' retrieved successfully.",
                'data' => $orders->items(),
                'pagination' => [
                    'current_page' => $orders->currentPage(),
                    'per_page' => $orders->perPage(),
                    'total' => $orders->total(),
                    'last_page' => $orders->lastPage(),
                ]
            ], 200);

        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve orders.',
                'error' => config('app.debug') === true ? $e->getMessage() : 'Internal server error.'
            ], 500);
        }
    }
}