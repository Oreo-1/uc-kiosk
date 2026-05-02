<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tabel food & vendor sudah memiliki foreign key yang benar 
        // dari migration create_food_table & create_vendor_table.
        // Migration fix ini tidak lagi diperlukan dan dikosongkan 
        // untuk menghindari konflik constraint.
        // File tetap disimpan agar version control tidak terganggu.
    }

    public function down(): void
    {
        // Tidak ada rollback yang diperlukan
    }
};