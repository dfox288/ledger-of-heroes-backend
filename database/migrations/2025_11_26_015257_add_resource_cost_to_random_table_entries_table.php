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
            $table->unsignedTinyInteger('resource_cost')->nullable()
                ->after('level')
                ->comment('Resource cost for this entry (ki points, sorcery points, etc.)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('random_table_entries', function (Blueprint $table) {
            $table->dropColumn('resource_cost');
        });
    }
};
