<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('executions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trade_decision_id')->constrained()->cascadeOnDelete();
            $table->enum('mode', ['paper', 'live'])->default('paper');
            $table->enum('status', ['pending', 'filled', 'failed', 'cancelled'])->default('pending');
            $table->string('exchange_order_id')->nullable();
            $table->string('asset_symbol', 10);
            $table->enum('action', ['buy', 'sell', 'hold']);
            $table->unsignedBigInteger('amount_usd')->default(0);        // cents
            $table->unsignedBigInteger('price_at_execution')->nullable(); // cents
            $table->unsignedBigInteger('fee_usd')->nullable();           // cents
            $table->text('failure_reason')->nullable();
            $table->timestamps();
            $table->index(['mode', 'status']);
            $table->index('created_at');
        });
    }

    public function down(): void { Schema::dropIfExists('executions'); }
};
