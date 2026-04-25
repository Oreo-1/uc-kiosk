<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Nonaktifkan Foreign Key Check sementara untuk menghindari error #1833
        DB::statement('SET FOREIGN_KEY_CHECKS = 0;');

        // 1. Tambahkan AUTO_INCREMENT pada kolom id (vendor, food, order)
        foreach (['vendor', 'food', 'order'] as $table) {
            if (Schema::hasTable($table)) {
                DB::statement("ALTER TABLE `{$table}` MODIFY `id` INT NOT NULL AUTO_INCREMENT");
            }
        }

        // 2. Hapus Foreign Key lama (jika ada) untuk menghindari konflik nama constraint
        $this->safeDropForeign('food', 'vendor_id');
        $this->safeDropForeign('order', 'vendor_id1');
        $this->safeDropForeign('order_food', 'food_id');
        $this->safeDropForeign('order_food', 'order_id');

        // 3. Tambahkan kembali Foreign Key dengan standar Laravel & CASCADE
        Schema::table('food', function ($table) {
            $table->foreign('vendor_id')->references('id')->on('vendor')->onDelete('cascade');
        });

        Schema::table('order', function ($table) {
            $table->foreign('vendor_id')->references('id')->on('vendor')->onDelete('cascade');
        });

        Schema::table('order_food', function ($table) {
            $table->foreign('food_id')->references('id')->on('food')->onDelete('cascade');
            $table->foreign('order_id')->references('id')->on('order')->onDelete('cascade');
        });

        // Aktifkan kembali Foreign Key Check
        DB::statement('SET FOREIGN_KEY_CHECKS = 1;');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS = 0;');

        // Hapus Foreign Key
        $this->safeDropForeign('food', 'vendor_id');
        $this->safeDropForeign('order', 'vendor_id1');
        $this->safeDropForeign('order_food', 'food_id');
        $this->safeDropForeign('order_food', 'order_id');

        // Hapus AUTO_INCREMENT (kembalikan ke kondisi awal)
        foreach (['vendor', 'food', 'order'] as $table) {
            if (Schema::hasTable($table)) {
                DB::statement("ALTER TABLE `{$table}` MODIFY `id` INT NOT NULL");
            }
        }

        DB::statement('SET FOREIGN_KEY_CHECKS = 1;');
    }

    /**
     * Helper: Hapus FK hanya jika constraint benar-benar ada (hindari error migration)
     */
    protected function safeDropForeign(string $table, string $keyName): void
    {
        try {
            Schema::table($table, fn ($t) => $t->dropForeign($keyName));
        } catch (\Exception $e) {
            // Constraint tidak ditemukan, aman untuk dilewati
        }
    }
};