<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            // equipment_mode: null = not yet chosen, 'equipment' = take starting equipment, 'gold' = take gold
            $table->string('equipment_mode', 20)->nullable()->after('background_slug');
        });

        // Migrate existing equipment_mode_marker data to the new column
        // Use PHP loop for SQLite compatibility
        $markers = DB::table('character_equipment')
            ->where('item_slug', 'equipment_mode_marker')
            ->get(['character_id', 'custom_description']);

        foreach ($markers as $marker) {
            $metadata = json_decode($marker->custom_description, true);
            $equipmentMode = $metadata['equipment_mode'] ?? null;

            if ($equipmentMode) {
                DB::table('characters')
                    ->where('id', $marker->character_id)
                    ->update(['equipment_mode' => $equipmentMode]);
            }
        }

        // Remove the old marker entries
        DB::table('character_equipment')
            ->where('item_slug', 'equipment_mode_marker')
            ->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Recreate markers from column data (for rollback)
        $characters = DB::table('characters')
            ->whereNotNull('equipment_mode')
            ->get(['id', 'equipment_mode']);

        foreach ($characters as $character) {
            DB::table('character_equipment')->insert([
                'character_id' => $character->id,
                'item_slug' => 'equipment_mode_marker',
                'quantity' => 0,
                'equipped' => false,
                'custom_description' => json_encode([
                    'source' => 'class',
                    'equipment_mode' => $character->equipment_mode,
                    'gold_amount' => null,
                ]),
            ]);
        }

        Schema::table('characters', function (Blueprint $table) {
            $table->dropColumn('equipment_mode');
        });
    }
};
