<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tabel food_addons sudah dibuat oleh migration sebelumnya 
        // (2026_04_28_000001_create_food_addons_and_add_parent_food_id.php)
        // File ini dikosongkan untuk menghindari duplikasi tabel.
        // File tetap disimpan agar version control tidak terganggu.
    }

    public function down(): void
    {
        // Tidak ada rollback yang diperlukan
    }
};