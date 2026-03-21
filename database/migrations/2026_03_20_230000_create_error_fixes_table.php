<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('error_fixes', function (Blueprint $table) {
            $table->id();
            $table->string('error_hash', 64)->unique(); // SHA-256, dedup
            $table->text('error_message');
            $table->text('error_context')->nullable();
            $table->text('fix_description');
            $table->text('fix_command')->nullable();
            $table->enum('fix_type', ['db', 'artisan', 'code', 'info'])->default('info');
            $table->boolean('fix_applied')->default(false);
            $table->text('fix_result')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('error_fixes');
    }
};
