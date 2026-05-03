<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('food', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendor')->onDelete('cascade');
            $table->string('name', 45)->unique();
            $table->enum('type', ['FOOD', 'DRINK', 'SNACK', 'PRASMANAN']);
            $table->decimal('price', 10, 2);
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->integer('estimated_time')->default(0);
            $table->enum('flavor_attribute', ['SENANG', 'SEDIH', 'MARAH', 'DATAR'])->nullable();
            $table->boolean('active')->default(true);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('food');
    }
};