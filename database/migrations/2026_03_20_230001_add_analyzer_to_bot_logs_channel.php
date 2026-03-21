<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') return;
        DB::statement("ALTER TABLE bot_logs MODIFY COLUMN channel ENUM('scraper','claude','executor','coinbase','scheduler','order_status','system','gemini','analyzer') NOT NULL");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') return;
        DB::statement("ALTER TABLE bot_logs MODIFY COLUMN channel ENUM('scraper','claude','executor','coinbase','scheduler','order_status','system','gemini') NOT NULL");
    }
};
