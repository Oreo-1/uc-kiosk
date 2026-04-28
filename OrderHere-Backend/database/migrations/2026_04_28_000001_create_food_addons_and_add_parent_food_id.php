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
        // 1. Buat tabel food_addons
        Schema::create('food_addons', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('food_id');
            $table->unsignedBigInteger('addons_id');
            $table->decimal('extra_price', 8, 2)->default(0);

            $table->foreign('food_id')->references('id')->on('food')->onDelete('cascade');
            $table->foreign('addons_id')->references('id')->on('food')->onDelete('cascade');
        });

        // 2. Tambah kolom parent_food_id ke tabel order_food
        Schema::table('order_food', function (Blueprint $table) {
            $table->unsignedBigInteger('parent_food_id')->nullable()->after('food_id');

            $table->foreign('parent_food_id')->references('id')->on('food')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Hapus FK dan kolom parent_food_id dari order_food
        Schema::table('order_food', function (Blueprint $table) {
            $table->dropForeign(['parent_food_id']);
            $table->dropColumn('parent_food_id');
        });

        // Hapus tabel food_addons
        Schema::dropIfExists('food_addons');
    }
};
