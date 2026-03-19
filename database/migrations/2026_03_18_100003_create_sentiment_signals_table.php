<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sentiment_signals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->string('asset_symbol', 10);
            $table->decimal('signal_score', 4, 3);
            $table->string('signal_type', 50);
            $table->timestamps();
            $table->index(['asset_symbol', 'created_at']);
        });
    }

    public function down(): void { Schema::dropIfExists('sentiment_signals'); }
};
