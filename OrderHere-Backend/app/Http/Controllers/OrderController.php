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
use Illuminate\Support\Facades\Log;

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

            if ($request->filled('vendor_id')) {
                $query->where('vendor_id', $request->input('vendor_id'));
            }
            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            }
            if ($request->filled('dining_type')) {
                $query->where('dining_type', $request->input('dining_type'));
            }
            if ($request->filled('queue_number')) {
                $query->where('queue_number', $request->input('queue_number'));
            }
            if ($request->filled('min_price')) {
                $query->where('total_price', '>=', $request->input('min_price'));
            }
            if ($request->filled('max_price')) {
                $query->where('total_price', '<=', $request->input('max_price'));
            }

            $perPage = $request->input('per_page', 10);
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
            Log::error('Order index failed:', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Database error occurred.',
                'error' => config('app.debug') === true ? $e->getMessage() : 'Silakan hubungi administrator.'
            ], 500);
        } catch (Throwable $e) {
            Log::error('Order index failed:', ['error' => $e->getMessage()]);
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
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'dining_type'  => ['required', Rule::in(['TAKEAWAY', 'DINEIN'])],
                'notes_order'  => ['nullable', 'string'],
                'foods'        => ['required', 'array', 'min:1'],
                'foods.*.food_id'  => ['required', 'integer', 'exists:food,id'],
                'foods.*.quantity' => ['required', 'integer', 'min:1'],
                'foods.*.notes'    => ['nullable', 'string', 'max:255'],
            ]);

            $firstFood = Food::findOrFail($validated['foods'][0]['food_id']);
            $vendorId = $firstFood->vendor_id;

            $foodIds = array_column($validated['foods'], 'food_id');
            $foods = Food::whereIn('id', $foodIds)->get();
            
            if ($foods->pluck('vendor_id')->unique()->count() > 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid cart.',
                    'error' => 'All items in an order must belong to the same vendor.'
                ], 400);
            }

            $today = now()->startOfDay();
            $todayOrderCount = Order::where('vendor_id', $vendorId)
                ->whereDate('created_at', $today)
                ->count();
            $queueNumber = $todayOrderCount + 1;

            $totalPrice = 0;
            $totalEstimated = 0;
            $groupedItems = [];

            foreach ($validated['foods'] as $item) {
                $foodId = $item['food_id'];
                
                if (!isset($groupedItems[$foodId])) {
                    $groupedItems[$foodId] = [
                        'quantity' => 0,
                        'notes' => $item['notes'] ?? null,
                    ];
                }
                
                $groupedItems[$foodId]['quantity'] += $item['quantity'];
                
                if (!empty($item['notes'])) {
                    $existingNotes = $groupedItems[$foodId]['notes'];
                    $groupedItems[$foodId]['notes'] = $existingNotes 
                        ? $existingNotes . ', ' . $item['notes'] 
                        : $item['notes'];
                }
            }

            $orderFoods = [];
            foreach ($groupedItems as $foodId => $itemData) {
                $food = $foods->firstWhere('id', $foodId);
                
                if (!$food || !$food->active) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Food not available.',
                        'error' => "Food '{$food->name}' is currently inactive."
                    ], 400);
                }

                $itemTotal = $food->price * $itemData['quantity'];
                $totalPrice += $itemTotal;
                $totalEstimated += $food->estimated_time * $itemData['quantity'];

                $orderFoods[] = [
                    'food_id'     => $food->id,
                    'quantity'    => $itemData['quantity'],
                    'total_price' => $itemTotal,
                    'notes'       => $itemData['notes'],
                ];
            }

            $order = DB::transaction(function () use ($vendorId, $validated, $queueNumber, $totalPrice, $totalEstimated, $orderFoods) {
                $order = Order::create([
                    'vendor_id'       => $vendorId,
                    'dining_type'     => $validated['dining_type'],
                    'notes_order'     => $validated['notes_order'] ?? null,
                    'status'          => 'PENDING', // ✅ Status default PENDING
                    'queue_number'    => $queueNumber,
                    'total_price'     => $totalPrice,
                    'total_estimated' => $totalEstimated,
                ]);

                foreach ($orderFoods as $foodItem) {
                    $order->foods()->attach($foodItem['food_id'], [
                        'quantity'       => $foodItem['quantity'],
                        'total_price'    => $foodItem['total_price'],
                        'notes'          => $foodItem['notes'],
                        'parent_food_id' => null,
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
            Log::error('Order store failed:', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Database error occurred.',
                'error' => config('app.debug') === true ? $e->getMessage() : 'Silakan hubungi administrator.'
            ], 500);
        } catch (Throwable $e) {
            Log::error('Order store failed:', ['error' => $e->getMessage()]);
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
            Log::error('Order show failed:', ['error' => $e->getMessage()]);
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

            $authenticatedVendor = $request->user();
            if (!$authenticatedVendor || $order->vendor_id !== $authenticatedVendor->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Forbidden.',
                    'error' => 'You can only update status of your own orders.'
                ], 403);
            }

            // ✅ Validasi status yang diizinkan
            $validated = $request->validate([
                'status' => [
                    'required',
                    Rule::in(['PENDING', 'ONPROGRESS', 'DIANTAR', 'DONE', 'CANCELLED'])
                ],
            ]);

            // ✅ Validasi transisi status yang valid
            $validTransitions = [
                'PENDING'    => ['ONPROGRESS', 'CANCELLED'],
                'ONPROGRESS' => ['DIANTAR', 'CANCELLED'],
                'DIANTAR'    => ['DONE', 'CANCELLED'],
                'DONE'       => [],
                'CANCELLED'  => [],
            ];

            $currentStatus = $order->status;
            $newStatus = $validated['status'];

            // Cek apakah status sama
            if ($currentStatus === $newStatus) {
                return response()->json([
                    'success' => false,
                    'message' => 'Status is already ' . $newStatus
                ], 400);
            }

            // Cek apakah transisi valid
            if (!in_array($newStatus, $validTransitions[$currentStatus] ?? [])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid status transition.',
                    'error' => "Cannot change status from {$currentStatus} to {$newStatus}."
                ], 400);
            }

            $order->update(['status' => $validated['status']]);

            return response()->json([
                'success' => true,
                'message' => 'Order status updated successfully.',
                'data' => $order->fresh()->load(['vendor', 'foods'])
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors()
            ], 422);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found.',
                'error' => 'The requested order does not exist.'
            ], 404);
        } catch (Throwable $e) {
            Log::error('Update order status failed:', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Update failed.',
                'error' => config('app.debug') === true ? $e->getMessage() : 'Internal server error.'
            ], 500);
        }
    }

    /**
     * Get all orders for authenticated vendor (AUTH REQUIRED).
     * GET /api/vendor/orders
     */
    public function myOrders(Request $request)
    {
        try {
            $authenticatedVendor = $request->user();
            if (!$authenticatedVendor) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated.',
                    'error' => 'Please login as vendor first.'
                ], 401);
            }

            $query = Order::with('foods')
                ->where('vendor_id', $authenticatedVendor->id)
                ->orderBy('id', 'desc');

            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            }
            if ($request->filled('dining_type')) {
                $query->where('dining_type', $request->input('dining_type'));
            }
            if ($request->filled('queue_number')) {
                $query->where('queue_number', $request->input('queue_number'));
            }
            if ($request->filled('date')) {
                $query->whereDate('created_at', $request->input('date'));
            }
            if ($request->filled('start_date') && $request->filled('end_date')) {
                $query->whereBetween('created_at', [
                    $request->input('start_date') . ' 00:00:00',
                    $request->input('end_date') . ' 23:59:59'
                ]);
            }

            $perPage = $request->input('per_page', 10);
            $orders = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Your orders retrieved successfully.',
                'data' => $orders->items(),
                'pagination' => [
                    'current_page' => $orders->currentPage(),
                    'per_page' => $orders->perPage(),
                    'total' => $orders->total(),
                    'last_page' => $orders->lastPage(),
                ]
            ], 200);

        } catch (Throwable $e) {
            Log::error('Get my orders failed:', ['error' => $e->getMessage()]);
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
            if (!in_array($status, ['PENDING', 'ONPROGRESS', 'DIANTAR', 'DONE', 'CANCELLED'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid status.',
                    'error' => 'Status must be PENDING, ONPROGRESS, DIANTAR, DONE, or CANCELLED.'
                ], 400);
            }

            $query = Order::with(['vendor', 'foods'])
                ->where('status', $status)
                ->orderBy('id', 'desc');

            if ($request->filled('vendor_id')) {
                $query->where('vendor_id', $request->input('vendor_id'));
            }
            if ($request->filled('dining_type')) {
                $query->where('dining_type', $request->input('dining_type'));
            }

            $perPage = $request->input('per_page', 10);
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
            Log::error('Order byStatus failed:', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve orders.',
                'error' => config('app.debug') === true ? $e->getMessage() : 'Internal server error.'
            ], 500);
        }
    }

    // ─────────────────────────────────────────────────────
    // ✅ MIDTRANS PAYMENT METHODS
    // ─────────────────────────────────────────────────────

    /**
     * Create Midtrans transaction (for popup payment)
     * POST /api/payment/create
     */
    public function createTransaction(Request $request)
    {
        try {
            $request->validate([
                'order_id' => 'required|string',
                'gross_amount' => 'required|numeric',
                'customer_name' => 'required|string',
                'customer_email' => 'required|email',
                'items' => 'required|array'
            ]);

            \Midtrans\Config::$serverKey = env('MIDTRANS_SERVER_KEY');
            \Midtrans\Config::$isProduction = env('MIDTRANS_IS_PRODUCTION', false);
            \Midtrans\Config::$isSanitized = true;
            \Midtrans\Config::$is3ds = true;

            $itemDetails = [];
            foreach ($request->items as $item) {
                $itemDetails[] = [
                    'id' => $item['id'],
                    'price' => (int) $item['price'],
                    'quantity' => (int) $item['quantity'],
                    'name' => $item['name']
                ];
            }

            $params = [
                'transaction_details' => [
                    'order_id' => $request->order_id,
                    'gross_amount' => (int) $request->gross_amount,
                ],
                'customer_details' => [
                    'first_name' => $request->customer_name,
                    'email' => $request->customer_email,
                ],
                'item_details' => $itemDetails,
            ];

            $snapToken = \Midtrans\Snap::getSnapToken($params);

            return response()->json([
                'success' => true,
                'snap_token' => $snapToken
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate QR Code langsung (dinamis per order)
     * POST /api/payment/generate-qris
     */
    public function generateQRIS(Request $request)
    {
        try {
            $request->validate([
                'order_id' => 'required|string',
                'gross_amount' => 'required|numeric',
            ]);

            \Midtrans\Config::$serverKey = env('MIDTRANS_SERVER_KEY');
            \Midtrans\Config::$isProduction = env('MIDTRANS_IS_PRODUCTION', false);
            \Midtrans\Config::$isSanitized = true;
            \Midtrans\Config::$is3ds = true;

            $params = [
                'transaction_details' => [
                    'order_id' => $request->order_id,
                    'gross_amount' => (int) $request->gross_amount,
                ],
                'customer_details' => [
                    'first_name' => $request->customer_name ?? 'Customer',
                    'email' => $request->customer_email ?? 'customer@example.com',
                ],
            ];

            $snapToken = \Midtrans\Snap::getSnapToken($params);
            $qrCodeUrl = "https://api.midtrans.com/v2/qr/" . $snapToken . "/qr-code";

            return response()->json([
                'success' => true,
                'snap_token' => $snapToken,
                'qr_code_url' => $qrCodeUrl
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle Midtrans notification (webhook)
     * POST /api/payment/notification
     */
    public function handleNotification(Request $request)
    {
        try {
            $notification = new \Midtrans\Notification();
            
            Log::info("Midtrans Notification - Order: {$notification->order_id}, Status: {$notification->transaction_status}");
            
            // TODO: Update status order di database berdasarkan notification->order_id
            
            return response()->json(['status' => 'success']);
        } catch (\Exception $e) {
            Log::error("Midtrans notification error: " . $e->getMessage());
            return response()->json(['status' => 'error'], 500);
        }
    }
}