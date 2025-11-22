<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Change items.charges_max from unsignedSmallInteger to string(50)
     *
     * **Rationale:**
     * Some magic items have variable charge capacity determined by dice rolls.
     * Examples:
     * - Luck Blade: "has 1d4-1 charges" (0-3 charges)
     * - Most items: "has 7 charges" (static value)
     *
     * Changing to string allows storing both:
     * - Dice formulas: "1d4-1", "1d6+2"
     * - Static values: "7", "36" (integers as strings)
     *
     * This maintains backward compatibility while enabling variable charge items.
     */
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            // Drop the existing index before changing column type
            $table->dropIndex(['charges_max']);

            // Change from unsignedSmallInteger to string
            $table->string('charges_max', 50)->nullable()->change();

            // Re-add index for filtering
            $table->index('charges_max');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            // Drop index
            $table->dropIndex(['charges_max']);

            // Convert back to unsignedSmallInteger
            // Note: This will fail if any dice formulas exist in the data
            $table->unsignedSmallInteger('charges_max')->nullable()->change();

            // Re-add index
            $table->index('charges_max');
        });
    }
};
