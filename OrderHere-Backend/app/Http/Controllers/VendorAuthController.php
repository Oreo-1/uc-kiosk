<?php

namespace App\Http\Controllers;

use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class VendorAuthController extends Controller
{
    /**
     * Register vendor baru
     * POST /api/v1/vendor/register
     */
    public function register(Request $request)
    {
        // Validate input
        $validator = Validator::make($request->all(), [
            'name'           => 'required|string|max:45',
            'phone_number'   => 'required|string|max:20|unique:vendor,phone_number',
            'password'       => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Create vendor dengan password hashed
            $vendor = Vendor::create([
                'name'           => $request->name,
                'phone_number'   => $request->phone_number,
                'password'       => Hash::make($request->password),
            ]);

            // Generate Sanctum token
            $token = $vendor->createToken('vendor_token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Registrasi berhasil.',
                'data' => [
                    'vendor' => $vendor,
                    'token'  => $token,
                ]
            ], 201);

        } catch (Throwable $e) {
            Log::error('Vendor registration error: ' . $e->getMessage());

            // Handle unique constraint error manually
            if (str_contains($e->getMessage(), 'Duplicate entry') || str_contains($e->getMessage(), '1062')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Nomor telepon sudah terdaftar.',
                    'errors' => ['phone_number' => ['Nomor ini sudah digunakan vendor lain.']]
                ], 409);
            }

            return response()->json([
                'success' => false,
                'message' => 'Registrasi gagal. Silakan coba lagi.',
                'error' => config('app.debug') === true ? $e->getMessage() : 'Internal server error.'
            ], 500);
        }
    }

    /**
     * Login vendor - Pakai NAME + PASSWORD
     * POST /api/v1/vendor/login
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'       => 'required|string|max:45',  // ✅ Login pakai name
            'password'   => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // ✅ Query ke tabel vendor berdasarkan NAME (bukan phone_number)
            $vendor = Vendor::where('name', $request->name)->first();

            if (!$vendor || !Hash::check($request->password, $vendor->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Nama vendor atau password salah.'
                ], 401);
            }

            // Generate token
            $token = $vendor->createToken('vendor_token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Login berhasil.',
                'data' => [
                    'vendor' => [
                        'id' => $vendor->id,
                        'name' => $vendor->name,
                        'phone_number' => $vendor->phone_number,
                    ],
                    'token' => $token,
                ]
            ], 200);

        } catch (Throwable $e) {
            Log::error('Vendor login error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Login gagal.',
                'error' => config('app.debug') === true ? $e->getMessage() : 'Internal server error.'
            ], 500);
        }
    }

    /**
     * Logout vendor
     * POST /api/v1/vendor/logout
     */
    public function logout(Request $request)
    {
        try {
            // Revoke token saat ini
            $request->user()?->currentAccessToken()?->delete();

            return response()->json([
                'success' => true,
                'message' => 'Logout berhasil.'
            ], 200);

        } catch (Throwable $e) {
            Log::error('Vendor logout error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Logout gagal.',
                'error' => config('app.debug') === true ? $e->getMessage() : 'Internal server error.'
            ], 500);
        }
    }

    /**
     * Get authenticated vendor data
     * GET /api/v1/vendor/me
     */
    public function me(Request $request)
    {
        $vendor = $request->user();

        if (!$vendor) {
            return response()->json([
                'success' => false,
                'message' => 'Vendor not authenticated',
            ], 401);
        }

        return response()->json([
            'success' => true,
            'data' => $vendor,
        ], 200);
    }
}