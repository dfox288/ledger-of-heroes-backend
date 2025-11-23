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
        Schema::table('entity_modifiers', function (Blueprint $table) {
            $table->unsignedTinyInteger('level')->nullable()->after('condition')
                ->comment('Character level when this modifier applies (e.g., ASI at level 4)');

            $table->index('level');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('entity_modifiers', function (Blueprint $table) {
            $table->dropIndex(['level']);
            $table->dropColumn('level');
        });
    }
};
