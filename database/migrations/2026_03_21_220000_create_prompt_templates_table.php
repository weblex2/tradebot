<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prompt_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();               // z.B. 'analysis_system', 'scoring_system'
            $table->string('name');                        // Anzeigename
            $table->text('description')->nullable();       // Was macht dieser Prompt, welche Platzhalter gibt es
            $table->longText('content');                   // Der eigentliche Prompt-Text
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prompt_templates');
    }
};
