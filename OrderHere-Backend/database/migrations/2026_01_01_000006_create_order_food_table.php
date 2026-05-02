<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_food', function (Blueprint $table) {
            $table->foreignId('order_id')->constrained('order')->onDelete('cascade');
            $table->foreignId('food_id')->constrained('food')->onDelete('cascade');
            $table->integer('quantity');
            $table->decimal('total_price', 10, 2);
            $table->string('notes', 255)->nullable();
            $table->primary(['order_id', 'food_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_food');
    }
};