<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('analyses', function (Blueprint $table) {
            $table->id();
            $table->string('triggered_by')->default('scheduler');
            $table->json('portfolio_snapshot');
            $table->json('articles_evaluated');
            $table->json('signals_summary');
            $table->longText('claude_reasoning')->nullable();
            $table->unsignedInteger('prompt_tokens')->nullable();
            $table->unsignedInteger('completion_tokens')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists('analyses'); }
};
