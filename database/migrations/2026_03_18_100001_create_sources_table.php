<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sources', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('url');
            $table->enum('category', ['news', 'social', 'blog', 'official', 'other'])->default('news');
            $table->decimal('weight', 4, 2)->default(1.00);
            $table->unsignedSmallInteger('refresh_minutes')->default(60);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_scraped_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists('sources'); }
};
