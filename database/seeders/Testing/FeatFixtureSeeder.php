<?php

namespace Database\Seeders\Testing;

use App\Models\AbilityScore;
use App\Models\EntityPrerequisite;
use App\Models\EntitySource;
use App\Models\Feat;
use App\Models\Modifier;
use App\Models\ProficiencyType;
use App\Models\Race;
use App\Models\Source;

class FeatFixtureSeeder extends FixtureSeeder
{
    protected function fixturePath(): string
    {
        return 'tests/fixtures/entities/feats.json';
    }

    protected function model(): string
    {
        return Feat::class;
    }

    protected function createFromFixture(array $item): void
    {
        // Generate full_slug from source (if available)
        $fullSlug = null;
        if (! empty($item['source'])) {
            $sourceCode = strtolower($item['source']);
            $fullSlug = $sourceCode.':'.$item['slug'];
        }

        // Create feat
        $feat = Feat::create([
            'name' => $item['name'],
            'slug' => $item['slug'],
            'full_slug' => $fullSlug,
            'description' => $item['description'],
            'prerequisites_text' => $item['prerequisites_text'],
        ]);

        // Create prerequisites
        if (! empty($item['prerequisites'])) {
            foreach ($item['prerequisites'] as $prerequisite) {
                $this->createPrerequisite($feat, $prerequisite);
            }
        }

        // Create ability score improvements
        if (! empty($item['ability_score_improvements'])) {
            foreach ($item['ability_score_improvements'] as $improvement) {
                $this->createAbilityScoreImprovement($feat, $improvement);
            }
        }

        // Create entity source
        if (! empty($item['source'])) {
            $source = Source::where('code', $item['source'])->first();
            if ($source) {
                EntitySource::create([
                    'reference_type' => Feat::class,
                    'reference_id' => $feat->id,
                    'source_id' => $source->id,
                    'pages' => $item['pages'] ?? null,
                ]);
            }
        }
    }

    /**
     * Create a prerequisite relationship for the feat.
     */
    protected function createPrerequisite(Feat $feat, array $prerequisite): void
    {
        $prerequisiteType = null;
        $prerequisiteId = null;

        // Resolve prerequisite based on type
        switch ($prerequisite['type']) {
            case 'AbilityScore':
                $prerequisiteType = AbilityScore::class;
                $abilityScore = AbilityScore::where('code', $prerequisite['value'])->first();
                $prerequisiteId = $abilityScore?->id;
                break;

            case 'Race':
                $prerequisiteType = Race::class;
                $race = Race::where('slug', $prerequisite['value'])->first();
                $prerequisiteId = $race?->id;
                break;

            case 'ProficiencyType':
                $prerequisiteType = ProficiencyType::class;
                $proficiencyType = ProficiencyType::where('slug', $prerequisite['value'])->first();
                $prerequisiteId = $proficiencyType?->id;
                break;
        }

        // Only create prerequisite if we found the referenced entity
        if ($prerequisiteType && $prerequisiteId) {
            EntityPrerequisite::create([
                'reference_type' => Feat::class,
                'reference_id' => $feat->id,
                'prerequisite_type' => $prerequisiteType,
                'prerequisite_id' => $prerequisiteId,
                'minimum_value' => $prerequisite['minimum_value'] ?? null,
                'description' => $prerequisite['description'] ?? null,
            ]);
        }
    }

    /**
     * Create ability score improvement modifier for the feat.
     */
    protected function createAbilityScoreImprovement(Feat $feat, array $improvement): void
    {
        // Resolve ability score by code (if specified)
        $abilityScoreId = null;
        if (! empty($improvement['ability'])) {
            $abilityScore = AbilityScore::where('code', $improvement['ability'])->first();
            $abilityScoreId = $abilityScore?->id;
        }

        // Create modifier
        Modifier::create([
            'reference_type' => Feat::class,
            'reference_id' => $feat->id,
            'modifier_category' => 'ability_score',
            'ability_score_id' => $abilityScoreId,
            'value' => $improvement['value'],
            'is_choice' => $improvement['is_choice'] ?? false,
            'choice_count' => $improvement['choice_count'] ?? null,
        ]);
    }
}
