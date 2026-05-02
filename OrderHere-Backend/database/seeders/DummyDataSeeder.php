<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DummyDataSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Buat Vendor
        $vendorId = DB::table('vendor')->insertGetId([
            'name' => 'Chick on Cup',
            'phone_number' => '081234567890',
            'password' => Hash::make('password123'),
        ]);

        // 2. Buat Makanan
        DB::table('food')->insert([
            ['vendor_id' => $vendorId, 'name' => 'Nasi Goreng', 'type' => 'FOOD', 'price' => 12000, 'estimated_time' => 20, 'active' => 1],
            ['vendor_id' => $vendorId, 'name' => 'Es Teh Manis', 'type' => 'DRINK', 'price' => 5000, 'estimated_time' => 5, 'active' => 1],
            ['vendor_id' => $vendorId, 'name' => 'Kentang Goreng', 'type' => 'SNACK', 'price' => 8000, 'estimated_time' => 10, 'active' => 1],
        ]);
    }
}