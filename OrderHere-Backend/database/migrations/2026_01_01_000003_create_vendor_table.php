<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor', function (Blueprint $table) {
            $table->id();
            $table->string('name', 45);
            $table->string('phone_number', 20)->unique();
            $table->string('password');
            $table->string('remember_token', 100)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor');
    }
};