<?php

namespace App\Services\Importers;

use App\Models\AbilityScore;
use App\Models\Feat;
use App\Models\Proficiency;
use App\Services\Importers\Concerns\ImportsConditions;
use App\Services\Importers\Concerns\ImportsEntitySpells;
use App\Services\Importers\Concerns\ImportsModifiers;
use App\Services\Importers\Concerns\ImportsPrerequisites;
use App\Services\Parsers\FeatXmlParser;

class FeatImporter extends BaseImporter
{
    use ImportsConditions;
    use ImportsEntitySpells;
    use ImportsModifiers;
    use ImportsPrerequisites;

    /**
     * Import a feat from parsed data.
     */
    protected function importEntity(array $data): Feat
    {
        // 1. Upsert feat using slug as unique key
        $feat = Feat::updateOrCreate(
            ['slug' => $this->generateSlug($data['name'])],
            [
                'name' => $data['name'],
                'prerequisites_text' => $data['prerequisites'] ?? null,
                'description' => $data['description'],
            ]
        );

        // 2. Clear existing polymorphic relationships
        $feat->proficiencies()->delete();
        $feat->prerequisites()->delete();
        $feat->sources()->delete();
        $feat->conditions()->delete();

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

        // 9. Refresh to load all relationships created during import
        $feat->refresh();

        return $feat;
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
}
