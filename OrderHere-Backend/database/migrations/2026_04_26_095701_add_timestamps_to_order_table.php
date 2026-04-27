<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 'order' is a reserved word, so we use backticks or raw statement for safety
        Schema::table('order', function (Blueprint $table) {
            $table->timestamp('created_at')->useCurrent()->after('total_estimated');
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate()->after('created_at');
        });
    }

    public function down(): void
    {
        Schema::table('order', function (Blueprint $table) {
            $table->dropColumn(['updated_at', 'created_at']);
        });
    }
};