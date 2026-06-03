<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Midtrans\Config;
use Midtrans\Snap;
use Midtrans\Notification;

class PaymentController extends Controller
{
    public function __construct()
    {
        Config::$serverKey = env('MIDTRANS_SERVER_KEY');
        Config::$isProduction = env('MIDTRANS_IS_PRODUCTION', false);
        Config::$isSanitized = true;
        Config::$is3ds = true;
    }

    public function createTransaction(Request $request)
    {
        $request->validate([
            'order_id' => 'required|string',
            'gross_amount' => 'required|numeric',
            'customer_name' => 'required|string',
            'customer_email' => 'required|email',
            'items' => 'required|array'
        ]);

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

        try {
            $snapToken = Snap::getSnapToken($params);

            return response()->json([
                'success' => true,
                'snap_token' => $snapToken,
                'message' => 'Transaction created successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create transaction: ' . $e->getMessage()
            ], 500);
        }
    }

    public function notification(Request $request)
    {
        $notification = new Notification();
        
        $transactionStatus = $notification->transaction_status;
        $orderId = $notification->order_id;

        \Log::info("Midtrans Notification - Order ID: {$orderId}, Status: {$transactionStatus}");

        return response()->json(['status' => 'success']);
    }
}
