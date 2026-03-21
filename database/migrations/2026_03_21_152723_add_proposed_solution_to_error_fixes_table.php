<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('error_fixes', function (Blueprint $table) {
            $table->text('proposed_solution')->nullable()->after('fix_description');
        });
    }

    public function down(): void
    {
        Schema::table('error_fixes', function (Blueprint $table) {
            $table->dropColumn('proposed_solution');
        });
    }
};
