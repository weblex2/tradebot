<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discussions', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('title_hash', 64)->unique(); // SHA-256 dedup
            $table->text('suggestion');                  // Claude's initial proposal
            $table->json('affected_files')->nullable();  // ['app/Services/Foo.php', ...]
            $table->enum('priority', ['low', 'medium', 'high'])->default('medium');
            $table->enum('status', ['pending', 'discussing', 'agreed', 'rejected', 'implementing', 'finished'])->default('pending');
            $table->json('turns')->nullable();           // [{role, content, at}]
            $table->tinyInteger('round')->default(0);   // 0–5
            $table->text('consensus_summary')->nullable();    // agreed implementation plan
            $table->text('implementation_notes')->nullable(); // what was actually changed
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discussions');
    }
};
