<?php

namespace App\Services\Importers\Concerns;

use App\Models\CharacterClass;
use App\Models\ClassLevelProgression;

/**
 * Trait for importing class spell slot progression.
 */
trait ImportsSpellProgression
{
    /**
     * Import spell progression (level progression with spell slots).
     */
    protected function importSpellProgression(CharacterClass $class, array $progression): void
    {
        foreach ($progression as $levelData) {
            // Use updateOrCreate to prevent duplicates on re-import
            // Unique key: class_id + level
            ClassLevelProgression::updateOrCreate(
                [
                    'class_id' => $class->id,
                    'level' => $levelData['level'],
                ],
                [
                    'cantrips_known' => $levelData['cantrips_known'],
                    'spell_slots_1st' => $levelData['spell_slots_1st'],
                    'spell_slots_2nd' => $levelData['spell_slots_2nd'],
                    'spell_slots_3rd' => $levelData['spell_slots_3rd'],
                    'spell_slots_4th' => $levelData['spell_slots_4th'],
                    'spell_slots_5th' => $levelData['spell_slots_5th'],
                    'spell_slots_6th' => $levelData['spell_slots_6th'],
                    'spell_slots_7th' => $levelData['spell_slots_7th'],
                    'spell_slots_8th' => $levelData['spell_slots_8th'],
                    'spell_slots_9th' => $levelData['spell_slots_9th'],
                    'spells_known' => $levelData['spells_known'] ?? null,
                ]
            );
        }
    }
}
