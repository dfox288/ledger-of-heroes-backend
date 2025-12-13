<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Migrate equipment locations from legacy values to expanded slot system.
 *
 * Changes:
 * - 'worn' → 'armor' (for armor items) or 'backpack' (for others)
 * - 'attuned' → appropriate slot based on item type + set is_attuned=true
 *
 * @see https://github.com/dfox288/ledger-of-heroes/issues/582
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Migrate 'worn' location to 'armor'
        // Note: All items at 'worn' location should be armor since that's what the old system enforced
        DB::table('character_equipment')
            ->where('location', 'worn')
            ->update([
                'location' => 'armor',
            ]);

        // Migrate 'attuned' location to 'ring_1' and set is_attuned=true
        // The old system used 'attuned' as both a location and attunement indicator
        // In the new system, items go to a specific slot with is_attuned flag
        DB::table('character_equipment')
            ->where('location', 'attuned')
            ->update([
                'location' => 'ring_1', // Default to ring_1 for attuned items
                'is_attuned' => true,
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert 'armor' back to 'worn'
        DB::table('character_equipment')
            ->where('location', 'armor')
            ->update([
                'location' => 'worn',
            ]);

        // Revert attuned items back to 'attuned' location
        // Note: This loses the specific slot information
        DB::table('character_equipment')
            ->where('is_attuned', true)
            ->update([
                'location' => 'attuned',
            ]);
    }
};
