<?php

namespace App\Services\Importers;

use App\Models\AbilityScore;
use App\Models\DamageType;
use App\Models\Feat;
use App\Models\Proficiency;
use App\Services\Importers\Concerns\ImportsConditions;
use App\Services\Importers\Concerns\ImportsEntitySpells;
use App\Services\Importers\Concerns\ImportsLanguages;
use App\Services\Importers\Concerns\ImportsModifiers;
use App\Services\Importers\Concerns\ImportsPrerequisites;
use App\Services\Parsers\FeatXmlParser;

class FeatImporter extends BaseImporter
{
    use ImportsConditions;
    use ImportsEntitySpells;
    use ImportsLanguages;
    use ImportsModifiers;
    use ImportsPrerequisites;

    /**
     * Import a feat from parsed data.
     */
    protected function importEntity(array $data): Feat
    {
        // Generate slug and full_slug
        $slug = $this->generateSlug($data['name']);
        $sources = $data['sources'] ?? [];
        $fullSlug = $this->generateFullSlug($slug, $sources);

        // 1. Upsert feat using slug as unique key
        $feat = Feat::updateOrCreate(
            ['slug' => $slug],
            [
                'name' => $data['name'],
                'full_slug' => $fullSlug,
                'prerequisites_text' => $data['prerequisites'] ?? null,
                'description' => $data['description'],
                'resets_on' => $data['resets_on'] ?? null,
            ]
        );

        // 2. Clear existing polymorphic relationships
        $feat->proficiencies()->delete();
        $feat->prerequisites()->delete();
        $feat->sources()->delete();
        $feat->conditions()->delete();
        $feat->languages()->delete();

        // 3. Import modifiers (convert ability_code to ability_score_id)
        $modifiersData = $this->prepareModifiersData($data['modifiers'] ?? []);
        $this->importEntityModifiers($feat, $modifiersData);

        // 4. Import proficiencies
        $this->importProficiencies($feat, $data['proficiencies'] ?? []);

        // 5. Import prerequisites (structured from parsed text)
        $this->importPrerequisites($feat, $data['prerequisites'] ?? null);

        // 6. Import conditions (advantages/disadvantages)
        $this->importEntityConditions($feat, $data['conditions'] ?? []);

        // 7. Import sources using trait
        $this->importEntitySources($feat, $data['sources'] ?? []);

        // 8. Import spells granted by feat
        $this->importEntitySpells($feat, $data['spells'] ?? []);

        // 9. Import languages granted by feat
        $this->importEntityLanguages($feat, $data['languages'] ?? []);

        // 10. Import movement modifiers
        $this->importMovementModifiers($feat, $data['movement_modifiers'] ?? []);

        // 11. Import damage resistances as modifiers
        $this->importDamageResistances($feat, $data['resistances'] ?? []);

        // 12. Refresh to load all relationships created during import
        $feat->refresh();

        return $feat;
    }

    /**
     * Import movement modifiers for a feat.
     *
     * Handles two types of movement modifiers:
     * - movement_cost: Changes to how movement costs are calculated (e.g., climbing, standing from prone)
     * - speed_bonus: Direct bonuses to movement speed (e.g., +10 feet walking speed)
     *
     * @param  array<int, array<string, mixed>>  $movementModifiers
     */
    private function importMovementModifiers(Feat $feat, array $movementModifiers): void
    {
        foreach ($movementModifiers as $modData) {
            $type = $modData['type'] ?? 'movement_cost';

            if ($type === 'speed_bonus') {
                // Speed bonus: "Your speed increases by X feet"
                // Category format: speed_bonus_{movement_type} (e.g., speed_bonus_walk)
                $movementType = $modData['movement_type'] ?? 'walk';
                $category = 'speed_bonus_'.$movementType;
                $value = (string) $modData['value'];

                $this->importModifier($feat, $category, [
                    'value' => $value,
                    'condition' => $modData['condition'] ?? null,
                ]);
            } else {
                // Movement cost: "Climbing doesn't cost extra movement"
                // Category format: movement_cost_{activity} (e.g., movement_cost_climbing)
                $category = 'movement_cost_'.$modData['activity'];

                // Convert all cost values to strings for consistent database storage
                // Cost can be 'normal' (no extra movement) or a number like 5 (feet)
                $value = is_int($modData['cost']) ? (string) $modData['cost'] : $modData['cost'];

                $this->importModifier($feat, $category, [
                    'value' => $value,
                    'condition' => $modData['condition'],
                ]);
            }
        }
    }

    /**
     * Prepare modifiers data by converting ability_code to ability_score_id.
     *
     * Preserves skill_name for ImportsModifiers trait to resolve to skill_id.
     */
    private function prepareModifiersData(array $modifiers): array
    {
        return array_map(function ($modifierData) {
            // Support both 'category' and 'modifier_category' keys
            $category = $modifierData['modifier_category'] ?? $modifierData['category'];

            $prepared = [
                'category' => $category,
                'value' => $modifierData['value'],
                'ability_score_id' => null,
                'skill_id' => null,
                'damage_type_id' => null,
            ];

            // For ability score modifiers, convert ability_code to ability_score_id
            if ($category === 'ability_score' && isset($modifierData['ability_code'])) {
                $abilityScore = AbilityScore::where('code', $modifierData['ability_code'])->first();
                if ($abilityScore) {
                    $prepared['ability_score_id'] = $abilityScore->id;
                }
            }

            // Preserve skill_name for ImportsModifiers trait to resolve
            if (isset($modifierData['skill_name'])) {
                $prepared['skill_name'] = $modifierData['skill_name'];
            }

            return $prepared;
        }, $modifiers);
    }

    /**
     * Import proficiencies for a feat.
     */
    private function importProficiencies(Feat $feat, array $proficiencies): void
    {
        foreach ($proficiencies as $profData) {
            // Determine proficiency type based on description
            $type = $this->determineProficiencyType($profData['description']);

            Proficiency::create([
                'reference_type' => Feat::class,
                'reference_id' => $feat->id,
                'proficiency_type' => $type,
                'proficiency_name' => $profData['description'],
                'proficiency_type_id' => null, // Could be enhanced with type matching later
                'skill_id' => null,
                'grants' => true,
                'is_choice' => $profData['is_choice'],
                'quantity' => $profData['quantity'] ?? 1,
            ]);
        }
    }

    /**
     * Determine proficiency type from description.
     */
    private function determineProficiencyType(string $description): string
    {
        $normalized = strtolower($description);

        return match (true) {
            str_contains($normalized, 'armor') => 'armor',
            str_contains($normalized, 'weapon') => 'weapon',
            str_contains($normalized, 'shield') => 'armor',
            str_contains($normalized, 'saving throw') => 'saving_throw',
            str_contains($normalized, 'skill') => 'skill',
            str_contains($normalized, 'tool') => 'tool',
            default => 'other',
        };
    }

    /**
     * Import prerequisites for a feat.
     */
    private function importPrerequisites(Feat $feat, ?string $prerequisiteText): void
    {
        if (empty($prerequisiteText)) {
            // Clear any existing prerequisites if no text provided
            $feat->prerequisites()->delete();

            return;
        }

        // Use parser to convert text to structured prerequisites
        $parser = new FeatXmlParser;
        $prerequisites = $parser->parsePrerequisites($prerequisiteText);

        // Delegate to the generalized trait method
        $this->importEntityPrerequisites($feat, $prerequisites);
    }

    public function getParser(): object
    {
        return new FeatXmlParser;
    }

    /**
     * Import damage resistances as modifiers for a feat.
     *
     * @param  array<int, array{damage_type: string, condition: string|null}>  $resistances
     */
    private function importDamageResistances(Feat $feat, array $resistances): void
    {
        foreach ($resistances as $resistanceData) {
            $damageTypeId = null;

            // For specific damage types, look up the DamageType model
            if ($resistanceData['damage_type'] !== 'all') {
                $damageType = DamageType::whereRaw('LOWER(name) = ?', [strtolower($resistanceData['damage_type'])])->first();

                if ($damageType) {
                    $damageTypeId = $damageType->id;
                } else {
                    // Skip if damage type not found (unless it's 'all')
                    continue;
                }
            }

            // Use the importModifier method from ImportsModifiers trait
            $this->importModifier($feat, 'damage_resistance', [
                'value' => 'resistance',
                'damage_type_id' => $damageTypeId,
                'condition' => $resistanceData['condition'] ?? null,
            ]);
        }
    }
}
