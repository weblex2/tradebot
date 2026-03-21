<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') return;
        Schema::table('bot_logs', function (Blueprint $table) {
            $table->text('message')->change();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') return;
        Schema::table('bot_logs', function (Blueprint $table) {
            $table->string('message')->change();
        });
    }
};
