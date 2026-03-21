<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sources', function (Blueprint $table) {
            $table->string('category', 50)->default('news')->change();
        });
    }

    public function down(): void
    {
        Schema::table('sources', function (Blueprint $table) {
            $table->enum('category', ['news', 'social', 'blog', 'official', 'other'])->default('news')->change();
        });
    }
};
