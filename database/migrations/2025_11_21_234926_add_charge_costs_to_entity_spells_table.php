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
        Schema::table('entity_spells', function (Blueprint $table) {
            $table->unsignedSmallInteger('charges_cost_min')->nullable()
                ->comment('Minimum charges to cast (0 = free, 1-50 = cost)');
            $table->unsignedSmallInteger('charges_cost_max')->nullable()
                ->comment('Maximum charges to cast (same as min for fixed costs)');
            $table->string('charges_cost_formula', 100)->nullable()
                ->comment('Human-readable formula: "1 per spell level", "1-3 per use"');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('entity_spells', function (Blueprint $table) {
            $table->dropColumn(['charges_cost_min', 'charges_cost_max', 'charges_cost_formula']);
        });
    }
};
