<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_id')->constrained()->cascadeOnDelete();
            $table->string('url', 2048);
            $table->string('title');
            $table->longText('content');
            $table->char('content_hash', 64)->unique();
            $table->timestamp('published_at')->nullable();
            $table->decimal('sentiment_score', 4, 3)->nullable();
            $table->boolean('is_processed')->default(false);
            $table->timestamps();
            $table->index(['source_id', 'is_processed']);
            $table->index('published_at');
        });
    }

    public function down(): void { Schema::dropIfExists('articles'); }
};
