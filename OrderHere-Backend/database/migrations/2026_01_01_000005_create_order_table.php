<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendor')->onDelete('cascade');
            $table->enum('dining_type', ['TAKEAWAY', 'DINEIN']);
            $table->enum('status', ['ONPROGRESS', 'DONE'])->default('ONPROGRESS');
            $table->integer('queue_number');
            $table->decimal('total_price', 10, 2);
            $table->integer('total_estimated')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order');
    }
};