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
        Schema::table('characters', function (Blueprint $table) {
            // JSON array of level numbers with resolved HP choices
            // e.g., [1, 2, 3] means levels 1-3 have HP resolved
            // Note: MySQL doesn't allow defaults on JSON, handled in model
            $table->json('hp_levels_resolved')->nullable()->after('temp_hit_points');

            // Enum: 'calculated' (auto) or 'manual' (legacy/custom)
            $table->string('hp_calculation_method', 10)->default('calculated')->after('hp_levels_resolved');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->dropColumn(['hp_levels_resolved', 'hp_calculation_method']);
        });
    }
};
