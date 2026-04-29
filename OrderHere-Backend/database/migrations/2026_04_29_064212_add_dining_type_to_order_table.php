<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order', function (Blueprint $table) {
            // Tambahkan setelah vendor_id atau status
            $table->enum('dining_type', ['TAKEAWAY', 'DINEIN'])
                  ->default('TAKEAWAY')
                  ->after('vendor_id');
        });
    }

    public function down(): void
    {
        Schema::table('order', function (Blueprint $table) {
            $table->dropColumn('dining_type');
        });
    }
};