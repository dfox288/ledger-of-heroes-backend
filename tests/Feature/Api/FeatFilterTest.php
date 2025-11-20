<?php

namespace Tests\Feature\Api;

use App\Models\AbilityScore;
use App\Models\Feat;
use App\Models\ProficiencyType;
use App\Models\Race;
use App\Models\Skill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FeatFilterTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    #[Test]
    public function it_filters_feats_by_prerequisite_race()
    {
        // Create Dwarf race
        $dwarf = Race::factory()->create(['name' => 'Dwarf', 'slug' => 'dwarf']);

        // Create feat requiring Dwarf
        $featWithPrereq = Feat::factory()->create(['name' => 'Dwarven Fortitude']);
        $featWithPrereq->prerequisites()->create([
            'prerequisite_type' => Race::class,
            'prerequisite_id' => $dwarf->id,
            'group_id' => 1,
        ]);

        // Create feat without prerequisites
        $featWithout = Feat::factory()->create(['name' => 'Alert']);

        // Test filter
        $response = $this->getJson('/api/v1/feats?prerequisite_race=dwarf');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Dwarven Fortitude');
    }

    #[Test]
    public function it_filters_feats_by_prerequisite_ability_score()
    {
        $strength = AbilityScore::where('code', 'STR')->first();

        $featWithStrPrereq = Feat::factory()->create(['name' => 'Grappler']);
        $featWithStrPrereq->prerequisites()->create([
            'prerequisite_type' => AbilityScore::class,
            'prerequisite_id' => $strength->id,
            'minimum_value' => 13,
            'group_id' => 1,
        ]);

        $featWithout = Feat::factory()->create(['name' => 'Alert']);

        // Test filter by ability
        $response = $this->getJson('/api/v1/feats?prerequisite_ability=strength');
        $response->assertOk();
        $response->assertJsonCount(1, 'data');

        // Test filter by ability + minimum value
        $response = $this->getJson('/api/v1/feats?prerequisite_ability=strength&min_value=13');
        $response->assertOk();
        $response->assertJsonCount(1, 'data');

        // Test with too high minimum
        $response = $this->getJson('/api/v1/feats?prerequisite_ability=strength&min_value=15');
        $response->assertOk();
        $response->assertJsonCount(0, 'data');
    }

    #[Test]
    public function it_filters_feats_without_prerequisites()
    {
        $featWithPrereq = Feat::factory()->create(['name' => 'Grappler', 'prerequisites_text' => 'Strength 13 or higher']);
        $featWithPrereq->prerequisites()->create([
            'prerequisite_type' => AbilityScore::class,
            'prerequisite_id' => AbilityScore::where('code', 'STR')->first()->id,
            'minimum_value' => 13,
            'group_id' => 1,
        ]);

        $featWithout = Feat::factory()->create(['name' => 'Alert', 'prerequisites_text' => null]);

        // Filter for feats WITHOUT prerequisites
        $response = $this->getJson('/api/v1/feats?has_prerequisites=false');
        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Alert');

        // Filter for feats WITH prerequisites
        $response = $this->getJson('/api/v1/feats?has_prerequisites=true');
        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Grappler');
    }

    #[Test]
    public function it_filters_feats_by_granted_proficiency()
    {
        $featGrantingProf = Feat::factory()->create(['name' => 'Weapon Master']);
        $featGrantingProf->proficiencies()->create([
            'proficiency_name' => 'Longsword',
            'proficiency_type' => 'weapon',
        ]);

        $featWithout = Feat::factory()->create(['name' => 'Alert']);

        $response = $this->getJson('/api/v1/feats?grants_proficiency=longsword');
        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Weapon Master');
    }

    #[Test]
    public function it_filters_feats_by_prerequisite_proficiency()
    {
        // Use seeded proficiency type
        $mediumArmor = ProficiencyType::where('name', 'LIKE', '%Medium Armor%')->first();

        // If not found, create one manually
        if (! $mediumArmor) {
            $mediumArmor = ProficiencyType::create([
                'name' => 'Medium Armor',
                'category' => 'armor',
            ]);
        }

        $featWithPrereq = Feat::factory()->create(['name' => 'Medium Armor Master']);
        $featWithPrereq->prerequisites()->create([
            'prerequisite_type' => ProficiencyType::class,
            'prerequisite_id' => $mediumArmor->id,
            'group_id' => 1,
        ]);

        $featWithout = Feat::factory()->create(['name' => 'Alert']);

        $response = $this->getJson('/api/v1/feats?prerequisite_proficiency=medium armor');
        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Medium Armor Master');
    }

    #[Test]
    public function it_filters_feats_by_granted_skill()
    {
        $insight = Skill::where('name', 'Insight')->first();

        $featGrantingSkill = Feat::factory()->create(['name' => 'Skilled']);
        $featGrantingSkill->proficiencies()->create([
            'proficiency_name' => 'Insight',
            'proficiency_type' => 'skill',
            'skill_id' => $insight?->id,
        ]);

        $featWithout = Feat::factory()->create(['name' => 'Alert']);

        $response = $this->getJson('/api/v1/feats?grants_skill=insight');
        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Skilled');
    }

    #[Test]
    public function it_combines_multiple_filters()
    {
        $strength = AbilityScore::where('code', 'STR')->first();

        // Feat with STR prerequisite AND grants proficiency
        $grappler = Feat::factory()->create(['name' => 'Grappler']);
        $grappler->prerequisites()->create([
            'prerequisite_type' => AbilityScore::class,
            'prerequisite_id' => $strength->id,
            'minimum_value' => 13,
            'group_id' => 1,
        ]);
        $athletics = Skill::where('name', 'Athletics')->first();
        $grappler->proficiencies()->create([
            'proficiency_name' => 'Athletics',
            'proficiency_type' => 'skill',
            'skill_id' => $athletics?->id,
        ]);

        // Feat with only STR prerequisite
        $heavyArmor = Feat::factory()->create(['name' => 'Heavy Armor Master']);
        $heavyArmor->prerequisites()->create([
            'prerequisite_type' => AbilityScore::class,
            'prerequisite_id' => $strength->id,
            'minimum_value' => 13,
            'group_id' => 1,
        ]);

        // Feat without prerequisites
        $alert = Feat::factory()->create(['name' => 'Alert']);

        // Test combining prerequisite + proficiency filters
        $response = $this->getJson('/api/v1/feats?prerequisite_ability=strength&grants_skill=athletics');
        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Grappler');
    }

    #[Test]
    public function it_handles_case_insensitive_searches()
    {
        $dwarf = Race::factory()->create(['name' => 'Dwarf', 'slug' => 'dwarf']);

        $feat = Feat::factory()->create(['name' => 'Dwarven Fortitude']);
        $feat->prerequisites()->create([
            'prerequisite_type' => Race::class,
            'prerequisite_id' => $dwarf->id,
            'group_id' => 1,
        ]);

        // Test with lowercase
        $response = $this->getJson('/api/v1/feats?prerequisite_race=dwarf');
        $response->assertOk();
        $response->assertJsonCount(1, 'data');

        // Test with uppercase
        $response = $this->getJson('/api/v1/feats?prerequisite_race=DWARF');
        $response->assertOk();
        $response->assertJsonCount(1, 'data');

        // Test with mixed case
        $response = $this->getJson('/api/v1/feats?prerequisite_race=DwArF');
        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    #[Test]
    public function it_returns_empty_results_when_no_matches()
    {
        $feat = Feat::factory()->create(['name' => 'Alert']);

        $response = $this->getJson('/api/v1/feats?prerequisite_race=elf');
        $response->assertOk();
        $response->assertJsonCount(0, 'data');

        $response = $this->getJson('/api/v1/feats?prerequisite_ability=intelligence');
        $response->assertOk();
        $response->assertJsonCount(0, 'data');
    }

    #[Test]
    public function it_paginates_filtered_results()
    {
        $strength = AbilityScore::where('code', 'STR')->first();

        // Create 25 feats with STR prerequisite
        for ($i = 1; $i <= 25; $i++) {
            $feat = Feat::factory()->create(['name' => "Feat {$i}"]);
            $feat->prerequisites()->create([
                'prerequisite_type' => AbilityScore::class,
                'prerequisite_id' => $strength->id,
                'minimum_value' => 13,
                'group_id' => 1,
            ]);
        }

        $response = $this->getJson('/api/v1/feats?prerequisite_ability=strength&per_page=10');
        $response->assertOk();
        $response->assertJsonCount(10, 'data');
        $response->assertJsonPath('meta.total', 25);
        $response->assertJsonPath('meta.per_page', 10);
    }
}
