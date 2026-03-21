<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE bot_logs MODIFY COLUMN channel ENUM('scraper','claude','executor','coinbase','scheduler','order_status','system','gemini','analyzer','discussions') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE bot_logs MODIFY COLUMN channel ENUM('scraper','claude','executor','coinbase','scheduler','order_status','system','gemini','analyzer') NOT NULL");
    }
};
