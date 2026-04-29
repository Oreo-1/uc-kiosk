<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('vendor', function (Blueprint $table) {
            // Nomor telepon untuk login (unik, nullable agar data lama tidak error saat migrate)
            $table->string('phone_number', 20)->nullable()->unique()->after('name');
            
            // Password untuk autentikasi (nullable sementara, nanti diisi saat register/update)
            $table->string('password')->nullable()->after('phone_number');
            
            // Token untuk fitur "Remember Me" (opsional tapi disarankan)
            $table->string('remember_token', 100)->nullable()->after('password');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vendor', function (Blueprint $table) {
            // Hapus unique index sebelum drop column
            $table->dropUnique(['phone_number']);
            $table->dropColumn(['phone_number', 'password', 'remember_token']);
        });
    }
};