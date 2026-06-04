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
        // Konfigurasi Midtrans
        Config::$serverKey = env('MIDTRANS_SERVER_KEY');
        Config::$isProduction = env('MIDTRANS_IS_PRODUCTION', false);
        Config::$isSanitized = true;
        Config::$is3ds = true;
    }

    /**
     * Create Midtrans transaction (untuk popup payment)
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

            $snapToken = Snap::getSnapToken($params);

            return response()->json([
                'success' => true,
                'snap_token' => $snapToken
            ]);
        } catch (\Exception $e) {
            \Log::error("Create transaction error: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate QR Code dinamis untuk QRIS (BERUBAH SETIAP TRANSAKSI)
     * POST /api/payment/generate-qris
     */
    public function generateQRIS(Request $request)
    {
        try {
            $request->validate([
                'order_id' => 'required|string',
                'gross_amount' => 'required|numeric',
            ]);

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

            // Dapatkan Snap Token dari Midtrans
            $snapToken = Snap::getSnapToken($params);
            
            // Generate URL QR Code dari Midtrans (ini yang akan berubah setiap transaksi)
            $qrCodeUrl = "https://api.midtrans.com/v2/qr/" . $snapToken . "/qr-code";

            \Log::info("QRIS Generated - Order ID: {$request->order_id}, Amount: {$request->gross_amount}");

            return response()->json([
                'success' => true,
                'snap_token' => $snapToken,
                'qr_code_url' => $qrCodeUrl,
                'payment_url' => 'https://app.midtrans.com/snap/v2/qr/' . $snapToken,
            ]);
        } catch (\Exception $e) {
            \Log::error("Generate QRIS error: " . $e->getMessage());
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
    public function notification(Request $request)
    {
        try {
            $notification = new Notification();
            
            $transactionStatus = $notification->transaction_status;
            $orderId = $notification->order_id;
            $fraudStatus = $notification->fraud_status;

            \Log::info("Midtrans Notification - Order ID: {$orderId}, Status: {$transactionStatus}");

            // TODO: Update status order di database berdasarkan $orderId
            
            return response()->json(['status' => 'success']);
        } catch (\Exception $e) {
            \Log::error("Midtrans notification error: " . $e->getMessage());
            return response()->json(['status' => 'error'], 500);
        }
    }
}