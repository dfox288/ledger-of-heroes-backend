<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Issue #763: Mark Path of the Totem Warrior features with choice_group values.
 *
 * Totem Warrior has three variant choice points:
 * - Level 3: Totem Spirit (Bear, Eagle, Wolf, Elk, Tiger)
 * - Level 6: Aspect of the Beast (Aspect of the Bear/Eagle/Wolf/Elk/Tiger)
 * - Level 14: Totemic Attunement (Bear, Eagle, Wolf, Elk, Tiger)
 *
 * The totem_spirit choice is made at subclass selection.
 * The totem_aspect and totem_attunement choices are made later via SubclassVariantChoiceHandler.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Get the Totem Warrior subclass ID
        $totemWarriorId = DB::table('classes')
            ->where('slug', 'phb:barbarian-path-of-the-totem-warrior')
            ->value('id');

        if (! $totemWarriorId) {
            return; // Totem Warrior not imported yet
        }

        // Level 3 - Totem Spirit variants
        // Pattern: "Animal (Path of the Totem Warrior)" at level 3
        $totemSpiritFeatures = [
            'Bear (Path of the Totem Warrior)',
            'Eagle (Path of the Totem Warrior)',
            'Wolf (Path of the Totem Warrior)',
            'Elk (Path of the Totem Warrior)',
            'Tiger (Path of the Totem Warrior)',
        ];

        DB::table('class_features')
            ->where('class_id', $totemWarriorId)
            ->where('level', 3)
            ->whereIn('feature_name', $totemSpiritFeatures)
            ->update(['choice_group' => 'totem_spirit']);

        // Level 6 - Aspect of the Beast variants
        // Pattern: "Aspect of the Animal (Path of the Totem Warrior)" at level 6
        $aspectFeatures = [
            'Aspect of the Bear (Path of the Totem Warrior)',
            'Aspect of the Eagle (Path of the Totem Warrior)',
            'Aspect of the Wolf (Path of the Totem Warrior)',
            'Aspect of the Elk (Path of the Totem Warrior)',
            'Aspect of the Tiger (Path of the Totem Warrior)',
        ];

        DB::table('class_features')
            ->where('class_id', $totemWarriorId)
            ->where('level', 6)
            ->whereIn('feature_name', $aspectFeatures)
            ->update(['choice_group' => 'totem_aspect']);

        // Level 14 - Totemic Attunement variants
        // Pattern: "Animal (Path of the Totem Warrior)" at level 14
        // Same names as L3 but different level
        DB::table('class_features')
            ->where('class_id', $totemWarriorId)
            ->where('level', 14)
            ->whereIn('feature_name', $totemSpiritFeatures)
            ->update(['choice_group' => 'totem_attunement']);
    }

    public function down(): void
    {
        // Get the Totem Warrior subclass ID
        $totemWarriorId = DB::table('classes')
            ->where('slug', 'phb:barbarian-path-of-the-totem-warrior')
            ->value('id');

        if (! $totemWarriorId) {
            return;
        }

        // Clear all choice_group values for Totem Warrior features
        DB::table('class_features')
            ->where('class_id', $totemWarriorId)
            ->whereIn('choice_group', ['totem_spirit', 'totem_aspect', 'totem_attunement'])
            ->update(['choice_group' => null]);
    }
};
