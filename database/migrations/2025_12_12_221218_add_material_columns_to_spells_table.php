<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add normalized material component columns to spells.
 *
 * Previously these were computed accessors parsing material_components text.
 * Now they're real columns populated during import for better accuracy and performance.
 *
 * Related: Issue #505
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spells', function (Blueprint $table) {
            $table->unsignedInteger('material_cost_gp')->nullable()->after('material_components')
                ->comment('Gold piece cost parsed from material_components');
            $table->boolean('material_consumed')->default(false)->after('material_cost_gp')
                ->comment('Whether material components are consumed by casting');
        });
    }

    public function down(): void
    {
        Schema::table('spells', function (Blueprint $table) {
            $table->dropColumn(['material_cost_gp', 'material_consumed']);
        });
    }
};
