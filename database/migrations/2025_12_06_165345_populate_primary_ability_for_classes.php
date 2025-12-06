<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Populate primary_ability field for D&D 5e classes.
 *
 * Primary abilities are essential for multiclass prerequisites (PHB p.163).
 * Some classes have multiple primary abilities (e.g., Monk: DEX and WIS).
 * Multiple abilities are stored as comma-separated values (e.g., "DEX, WIS").
 *
 * Source: Player's Handbook (2014) p.163
 */
return new class extends Migration
{
    /**
     * Primary abilities by class slug (PHB p.163).
     * Multiple abilities = player can use either for multiclass prereq.
     */
    private const PRIMARY_ABILITIES = [
        'artificer' => 'INT',
        'barbarian' => 'STR',
        'bard' => 'CHA',
        'cleric' => 'WIS',
        'druid' => 'WIS',
        'fighter' => 'STR or DEX',       // Player chooses (melee vs ranged)
        'monk' => 'DEX and WIS',          // Needs 13+ in both
        'paladin' => 'STR and CHA',       // Needs 13+ in both
        'ranger' => 'DEX and WIS',        // Needs 13+ in both
        'rogue' => 'DEX',
        'sorcerer' => 'CHA',
        'warlock' => 'CHA',
        'wizard' => 'INT',
    ];

    public function up(): void
    {
        foreach (self::PRIMARY_ABILITIES as $slug => $primaryAbility) {
            DB::table('classes')
                ->where('slug', $slug)
                ->whereNull('parent_class_id')  // Only base classes
                ->update(['primary_ability' => $primaryAbility]);
        }
    }

    public function down(): void
    {
        DB::table('classes')
            ->whereIn('slug', array_keys(self::PRIMARY_ABILITIES))
            ->whereNull('parent_class_id')
            ->update(['primary_ability' => null]);
    }
};
