<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds charge-based magic item mechanics to items table:
     * - charges_max: Total charge capacity (3, 7, 10, 36, 50)
     * - recharge_formula: How charges regenerate ("1d6+1", "all", "1d3", "1d20")
     * - recharge_timing: When charges regenerate ("dawn", "dusk", "short rest", "long rest")
     *
     * Affects ~70 items (3% of database): Wands, Staffs, Rings, Helms, etc.
     */
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->unsignedSmallInteger('charges_max')->nullable()
                ->comment('Maximum charge capacity (e.g., 3, 7, 36)')
                ->after('requires_attunement');

            $table->string('recharge_formula', 50)->nullable()
                ->comment('Recharge rate: "1d6+1", "all", "1d3", etc.')
                ->after('charges_max');

            $table->string('recharge_timing', 50)->nullable()
                ->comment('When charges regenerate: "dawn", "dusk", "short rest", "long rest"')
                ->after('recharge_formula');

            // Index for filtering items by charge capacity
            $table->index('charges_max');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropIndex(['charges_max']);
            $table->dropColumn(['charges_max', 'recharge_formula', 'recharge_timing']);
        });
    }
};
