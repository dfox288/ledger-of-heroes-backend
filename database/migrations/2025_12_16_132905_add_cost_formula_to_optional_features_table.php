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
        Schema::table('optional_features', function (Blueprint $table) {
            $table->string('cost_formula', 50)
                ->nullable()
                ->after('resource_cost')
                ->comment('Formula for variable costs (e.g., spell_level for Twinned Spell)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('optional_features', function (Blueprint $table) {
            $table->dropColumn('cost_formula');
        });
    }
};
