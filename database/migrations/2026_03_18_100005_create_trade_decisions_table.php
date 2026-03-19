<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('trade_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('analysis_id')->constrained()->cascadeOnDelete();
            $table->string('asset_symbol', 10);
            $table->enum('action', ['buy', 'sell', 'hold']);
            $table->unsignedTinyInteger('confidence');
            $table->unsignedBigInteger('amount_usd')->default(0); // cents
            $table->decimal('stop_loss_pct', 5, 2)->nullable();
            $table->decimal('take_profit_pct', 5, 2)->nullable();
            $table->text('rationale')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();
            $table->index(['asset_symbol', 'action']);
            $table->index('expires_at');
        });
    }

    public function down(): void { Schema::dropIfExists('trade_decisions'); }
};
