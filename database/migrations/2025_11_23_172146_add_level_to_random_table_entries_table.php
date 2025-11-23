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
        Schema::table('random_table_entries', function (Blueprint $table) {
            $table->unsignedTinyInteger('level')->nullable()->after('result_text')
                ->comment('Character level when this roll becomes available (e.g., Sneak Attack at level 3)');

            $table->index('level');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('random_table_entries', function (Blueprint $table) {
            $table->dropIndex(['level']);
            $table->dropColumn('level');
        });
    }
};
