<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tabel personal_access_tokens sudah dibuat oleh migration 
        // 2026_04_25_065442_create_personal_access_tokens_table.php
        // Migration ini dikosongkan untuk menghindari duplikasi.
        // File tetap disimpan untuk version control.
    }

    public function down(): void
    {
        // Tidak ada yang perlu di-rollback
    }
};