<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bot_logs', function (Blueprint $table) {
            $table->id();
            $table->enum('channel', ['scraper', 'claude', 'executor', 'coinbase', 'scheduler', 'order_status', 'system']);
            $table->enum('level', ['debug', 'info', 'warning', 'error']);
            $table->string('message');
            $table->json('context')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->unsignedBigInteger('analysis_id')->nullable();
            $table->unsignedBigInteger('execution_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['level', 'created_at']);
            $table->index(['channel', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_logs');
    }
};
