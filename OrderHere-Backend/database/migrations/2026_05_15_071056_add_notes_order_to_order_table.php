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
        Schema::table('order', function (Blueprint $table) {
            // Menambahkan kolom notes_order dengan tipe longText
            // nullable() digunakan agar kolom ini boleh kosong
            // after('total_estimated') untuk meletakkan kolom setelah total_estimated
            $table->longText('notes_order')->nullable()->after('total_estimated');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order', function (Blueprint $table) {
            $table->dropColumn('notes_order');
        });
    }
};