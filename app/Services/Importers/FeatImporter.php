<?php

namespace App\Services\Importers;

use App\Models\AbilityScore;
use App\Models\EntityCondition;
use App\Models\EntityPrerequisite;
use App\Models\Feat;
use App\Models\Modifier;
use App\Models\Proficiency;
use App\Services\Parsers\FeatXmlParser;

class FeatImporter extends BaseImporter
{
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
        $feat->modifiers()->delete();
        $feat->proficiencies()->delete();
        $feat->prerequisites()->delete();
        $feat->sources()->delete();
        $feat->conditions()->delete();

        // 3. Import modifiers
        $this->importModifiers($feat, $data['modifiers'] ?? []);

        // 4. Import proficiencies
        $this->importProficiencies($feat, $data['proficiencies'] ?? []);

        // 5. Import prerequisites (structured from parsed text)
        $this->importPrerequisites($feat, $data['prerequisites'] ?? null);

        // 6. Import conditions (advantages/disadvantages)
        $this->importConditions($feat, $data['conditions'] ?? []);

        // 7. Import sources using trait
        $this->importEntitySources($feat, $data['sources'] ?? []);

        return $feat;
    }

    /**
     * Import modifiers for a feat.
     */
    private function importModifiers(Feat $feat, array $modifiers): void
    {
        foreach ($modifiers as $modifierData) {
            $modifier = [
                'reference_type' => Feat::class,
                'reference_id' => $feat->id,
                'modifier_category' => $modifierData['category'], // Use correct field name
                'value' => $modifierData['value'],
                'ability_score_id' => null,
                'skill_id' => null,
                'damage_type_id' => null,
            ];

            // For ability score modifiers, look up the ability score ID
            if ($modifierData['category'] === 'ability_score' && isset($modifierData['ability_code'])) {
                $abilityScore = AbilityScore::where('code', $modifierData['ability_code'])->first();
                if ($abilityScore) {
                    $modifier['ability_score_id'] = $abilityScore->id;
                }
            }

            Modifier::create($modifier);
        }
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
     * Import conditions (advantages/disadvantages) for a feat.
     */
    private function importConditions(Feat $feat, array $conditions): void
    {
        foreach ($conditions as $conditionData) {
            EntityCondition::create([
                'reference_type' => Feat::class,
                'reference_id' => $feat->id,
                'condition_id' => null, // Conditions are stored as text descriptions, not FK references
                'effect_type' => $conditionData['effect_type'],
                'description' => $conditionData['description'],
            ]);
        }
    }

    /**
     * Import prerequisites for a feat.
     */
    private function importPrerequisites(Feat $feat, ?string $prerequisiteText): void
    {
        if (empty($prerequisiteText)) {
            return;
        }

        // Use parser to convert text to structured prerequisites
        $parser = new FeatXmlParser;
        $prerequisites = $parser->parsePrerequisites($prerequisiteText);

        // Create EntityPrerequisite records
        foreach ($prerequisites as $prereqData) {
            EntityPrerequisite::create([
                'reference_type' => Feat::class,
                'reference_id' => $feat->id,
                'prerequisite_type' => $prereqData['prerequisite_type'],
                'prerequisite_id' => $prereqData['prerequisite_id'],
                'minimum_value' => $prereqData['minimum_value'],
                'description' => $prereqData['description'],
                'group_id' => $prereqData['group_id'],
            ]);
        }
    }

    /**
     * Import feats from an XML file.
     *
     * @return int Number of feats imported
     */
    public function importFromFile(string $filePath): int
    {
        if (! file_exists($filePath)) {
            throw new \InvalidArgumentException("File not found: {$filePath}");
        }

        $xmlContent = file_get_contents($filePath);
        $parser = new FeatXmlParser;
        $feats = $parser->parse($xmlContent);

        $count = 0;
        foreach ($feats as $featData) {
            $this->import($featData);
            $count++;
        }

        return $count;
    }
}
