<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // For MySQL we can use a raw statement to change the enum
        DB::statement("ALTER TABLE bot_logs MODIFY COLUMN channel ENUM('scraper', 'claude', 'executor', 'coinbase', 'scheduler', 'order_status', 'system', 'gemini') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE bot_logs MODIFY COLUMN channel ENUM('scraper', 'claude', 'executor', 'coinbase', 'scheduler', 'order_status', 'system') NOT NULL");
    }
};
