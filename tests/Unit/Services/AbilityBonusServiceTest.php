<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\AbilityScore;
use App\Models\Character;
use App\Models\CharacterAbilityScore;
use App\Models\CharacterFeature;
use App\Models\Feat;
use App\Models\Modifier;
use App\Models\Race;
use App\Services\AbilityBonusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AbilityBonusServiceTest extends TestCase
{
    use RefreshDatabase;

    private AbilityBonusService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AbilityBonusService;
    }

    // =====================
    // getBonuses Tests
    // =====================

    #[Test]
    public function it_returns_empty_bonuses_for_character_with_no_race(): void
    {
        $character = Character::factory()->create(['race_slug' => null]);

        $result = $this->service->getBonuses($character);

        $this->assertCount(0, $result['bonuses']);
        $this->assertEquals([
            'STR' => 0,
            'DEX' => 0,
            'CON' => 0,
            'INT' => 0,
            'WIS' => 0,
            'CHA' => 0,
        ], $result['totals']);
    }

    #[Test]
    public function it_returns_fixed_racial_bonuses(): void
    {
        // Create race with fixed ability score bonus
        $race = Race::factory()->create([
            'slug' => 'dwarf',
            'full_slug' => 'phb:dwarf',
            'name' => 'Dwarf',
        ]);

        $conAbility = AbilityScore::where('code', 'CON')->first();

        Modifier::create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'modifier_category' => 'ability_score',
            'ability_score_id' => $conAbility->id,
            'value' => '2',
            'is_choice' => false,
        ]);

        $character = Character::factory()->create(['race_slug' => $race->full_slug]);

        $result = $this->service->getBonuses($character);

        $this->assertCount(1, $result['bonuses']);
        $bonus = $result['bonuses']->first();
        $this->assertEquals('race', $bonus['source_type']);
        $this->assertEquals('Dwarf', $bonus['source_name']);
        $this->assertEquals('phb:dwarf', $bonus['source_slug']);
        $this->assertEquals('CON', $bonus['ability_code']);
        $this->assertEquals('Constitution', $bonus['ability_name']);
        $this->assertEquals(2, $bonus['value']);
        $this->assertFalse($bonus['is_choice']);

        $this->assertEquals(2, $result['totals']['CON']);
    }

    #[Test]
    public function it_returns_resolved_racial_choice_bonuses_with_metadata(): void
    {
        $race = Race::factory()->create([
            'slug' => 'half-elf',
            'full_slug' => 'phb:half-elf',
            'name' => 'Half-Elf',
        ]);

        $chaAbility = AbilityScore::where('code', 'CHA')->first();

        // Fixed +2 CHA
        Modifier::create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'modifier_category' => 'ability_score',
            'ability_score_id' => $chaAbility->id,
            'value' => '2',
            'is_choice' => false,
        ]);

        // Choice: +1 to two different abilities
        $choiceModifier = Modifier::create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'modifier_category' => 'ability_score',
            'value' => '1',
            'is_choice' => true,
            'choice_count' => 2,
        ]);

        $character = Character::factory()->create(['race_slug' => $race->full_slug]);

        // Resolve choices: +1 STR, +1 DEX
        CharacterAbilityScore::create([
            'character_id' => $character->id,
            'ability_score_code' => 'STR',
            'bonus' => 1,
            'source' => 'race',
            'modifier_id' => $choiceModifier->id,
        ]);

        CharacterAbilityScore::create([
            'character_id' => $character->id,
            'ability_score_code' => 'DEX',
            'bonus' => 1,
            'source' => 'race',
            'modifier_id' => $choiceModifier->id,
        ]);

        $result = $this->service->getBonuses($character);

        $this->assertCount(3, $result['bonuses']);

        // Find the choice bonuses
        $strBonus = $result['bonuses']->firstWhere('ability_code', 'STR');
        $this->assertTrue($strBonus['is_choice']);
        $this->assertTrue($strBonus['choice_resolved']);
        $this->assertEquals($choiceModifier->id, $strBonus['modifier_id']);
        $this->assertEquals(1, $strBonus['value']);

        $dexBonus = $result['bonuses']->firstWhere('ability_code', 'DEX');
        $this->assertTrue($dexBonus['is_choice']);
        $this->assertTrue($dexBonus['choice_resolved']);

        $this->assertEquals([
            'STR' => 1,
            'DEX' => 1,
            'CON' => 0,
            'INT' => 0,
            'WIS' => 0,
            'CHA' => 2,
        ], $result['totals']);
    }

    #[Test]
    public function it_handles_subrace_inheritance_with_parent_and_child_modifiers(): void
    {
        // Create parent race (Elf) with +2 DEX
        $parentRace = Race::factory()->create([
            'slug' => 'elf',
            'full_slug' => 'phb:elf',
            'name' => 'Elf',
        ]);

        $dexAbility = AbilityScore::where('code', 'DEX')->first();
        Modifier::create([
            'reference_type' => Race::class,
            'reference_id' => $parentRace->id,
            'modifier_category' => 'ability_score',
            'ability_score_id' => $dexAbility->id,
            'value' => '2',
            'is_choice' => false,
        ]);

        // Create subrace (High Elf) with +1 INT
        $subrace = Race::factory()->create([
            'slug' => 'high-elf',
            'full_slug' => 'phb:high-elf',
            'name' => 'High Elf',
            'parent_race_id' => $parentRace->id,
        ]);

        $intAbility = AbilityScore::where('code', 'INT')->first();
        Modifier::create([
            'reference_type' => Race::class,
            'reference_id' => $subrace->id,
            'modifier_category' => 'ability_score',
            'ability_score_id' => $intAbility->id,
            'value' => '1',
            'is_choice' => false,
        ]);

        $character = Character::factory()->create(['race_slug' => $subrace->full_slug]);

        $result = $this->service->getBonuses($character);

        $this->assertCount(2, $result['bonuses']);

        // Should have +2 DEX from parent and +1 INT from subrace
        $dexBonus = $result['bonuses']->firstWhere('ability_code', 'DEX');
        $this->assertEquals(2, $dexBonus['value']);
        $this->assertEquals('High Elf', $dexBonus['source_name']);

        $intBonus = $result['bonuses']->firstWhere('ability_code', 'INT');
        $this->assertEquals(1, $intBonus['value']);
        $this->assertEquals('High Elf', $intBonus['source_name']);

        $this->assertEquals([
            'STR' => 0,
            'DEX' => 2,
            'CON' => 0,
            'INT' => 1,
            'WIS' => 0,
            'CHA' => 0,
        ], $result['totals']);
    }

    #[Test]
    public function it_returns_feat_bonuses_from_character_features(): void
    {
        $feat = Feat::factory()->create([
            'slug' => 'actor',
            'full_slug' => 'phb:actor',
            'name' => 'Actor',
        ]);

        $chaAbility = AbilityScore::where('code', 'CHA')->first();

        Modifier::create([
            'reference_type' => Feat::class,
            'reference_id' => $feat->id,
            'modifier_category' => 'ability_score',
            'ability_score_id' => $chaAbility->id,
            'value' => '1',
            'is_choice' => false,
        ]);

        $character = Character::factory()->create();

        CharacterFeature::create([
            'character_id' => $character->id,
            'feature_type' => Feat::class,
            'feature_id' => $feat->id,
            'feature_slug' => $feat->full_slug,
            'source' => 'feat',
            'level_acquired' => 1,
        ]);

        $result = $this->service->getBonuses($character);

        $this->assertCount(1, $result['bonuses']);
        $bonus = $result['bonuses']->first();
        $this->assertEquals('feat', $bonus['source_type']);
        $this->assertEquals('Actor', $bonus['source_name']);
        $this->assertEquals('phb:actor', $bonus['source_slug']);
        $this->assertEquals('CHA', $bonus['ability_code']);
        $this->assertEquals('Charisma', $bonus['ability_name']);
        $this->assertEquals(1, $bonus['value']);
        $this->assertFalse($bonus['is_choice']);

        $this->assertEquals(1, $result['totals']['CHA']);
    }

    #[Test]
    public function it_combines_race_and_feat_bonuses_correctly(): void
    {
        // Create race with +2 CHA
        $race = Race::factory()->create([
            'slug' => 'tiefling',
            'full_slug' => 'phb:tiefling',
            'name' => 'Tiefling',
        ]);

        $chaAbility = AbilityScore::where('code', 'CHA')->first();
        Modifier::create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'modifier_category' => 'ability_score',
            'ability_score_id' => $chaAbility->id,
            'value' => '2',
            'is_choice' => false,
        ]);

        // Create feat with +1 CHA (Actor)
        $feat = Feat::factory()->create([
            'slug' => 'actor',
            'full_slug' => 'phb:actor',
            'name' => 'Actor',
        ]);

        Modifier::create([
            'reference_type' => Feat::class,
            'reference_id' => $feat->id,
            'modifier_category' => 'ability_score',
            'ability_score_id' => $chaAbility->id,
            'value' => '1',
            'is_choice' => false,
        ]);

        $character = Character::factory()->create(['race_slug' => $race->full_slug]);

        CharacterFeature::create([
            'character_id' => $character->id,
            'feature_type' => Feat::class,
            'feature_id' => $feat->id,
            'feature_slug' => $feat->full_slug,
            'source' => 'feat',
            'level_acquired' => 1,
        ]);

        $result = $this->service->getBonuses($character);

        $this->assertCount(2, $result['bonuses']);

        // Total should be 3 (+2 from race, +1 from feat)
        $this->assertEquals(3, $result['totals']['CHA']);
    }

    #[Test]
    public function it_ignores_modifiers_that_are_not_ability_score_category(): void
    {
        $race = Race::factory()->create();

        $strAbility = AbilityScore::where('code', 'STR')->first();

        // Create a skill modifier (should be ignored)
        Modifier::create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'modifier_category' => 'skill',
            'value' => '2',
            'is_choice' => false,
        ]);

        // Create an ability score modifier (should be included)
        Modifier::create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'modifier_category' => 'ability_score',
            'ability_score_id' => $strAbility->id,
            'value' => '2',
            'is_choice' => false,
        ]);

        $character = Character::factory()->create(['race_slug' => $race->full_slug]);

        $result = $this->service->getBonuses($character);

        $this->assertCount(1, $result['bonuses']);
        $this->assertEquals('STR', $result['bonuses']->first()['ability_code']);
    }

    #[Test]
    public function it_excludes_choice_bonuses_with_no_resolved_selections(): void
    {
        $race = Race::factory()->create();

        // Create choice modifier with no character selections
        Modifier::create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'modifier_category' => 'ability_score',
            'value' => '1',
            'is_choice' => true,
            'choice_count' => 2,
        ]);

        $character = Character::factory()->create(['race_slug' => $race->full_slug]);

        $result = $this->service->getBonuses($character);

        // Unresolved choices should not appear in bonuses
        $this->assertCount(0, $result['bonuses']);
        $this->assertEquals([
            'STR' => 0,
            'DEX' => 0,
            'CON' => 0,
            'INT' => 0,
            'WIS' => 0,
            'CHA' => 0,
        ], $result['totals']);
    }

    #[Test]
    public function it_returns_feat_bonuses_when_feat_selected_via_feat_choice_service(): void
    {
        // Create a race with a bonus feat modifier (like Custom Lineage)
        $race = Race::factory()->create([
            'slug' => 'custom-lineage',
            'full_slug' => 'tce:custom-lineage',
            'name' => 'Custom Lineage',
        ]);

        Modifier::create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'modifier_category' => 'bonus_feat',
            'value' => '1',
            'is_choice' => false,
        ]);

        // Create Actor feat with +1 CHA
        $feat = Feat::factory()->create([
            'slug' => 'actor',
            'full_slug' => 'phb:actor',
            'name' => 'Actor',
        ]);

        $chaAbility = AbilityScore::where('code', 'CHA')->first();

        Modifier::create([
            'reference_type' => Feat::class,
            'reference_id' => $feat->id,
            'modifier_category' => 'ability_score',
            'ability_score_id' => $chaAbility->id,
            'value' => '1',
            'is_choice' => false,
        ]);

        $character = Character::factory()->create(['race_slug' => $race->full_slug]);

        // Select the feat via the FeatChoiceService (simulates the real workflow)
        $featChoiceService = app(\App\Services\FeatChoiceService::class);
        $featChoiceService->makeChoice($character, 'race', $feat->full_slug);

        // Verify the feat bonus appears
        $result = $this->service->getBonuses($character);

        $featBonuses = $result['bonuses']->where('source_type', 'feat');
        $this->assertCount(1, $featBonuses);

        $bonus = $featBonuses->first();
        $this->assertEquals('feat', $bonus['source_type']);
        $this->assertEquals('Actor', $bonus['source_name']);
        $this->assertEquals('phb:actor', $bonus['source_slug']);
        $this->assertEquals('CHA', $bonus['ability_code']);
        $this->assertEquals(1, $bonus['value']);

        $this->assertEquals(1, $result['totals']['CHA']);
    }

    #[Test]
    public function it_returns_feat_bonuses_when_feature_id_is_null_but_slug_exists(): void
    {
        // This tests the bug from issue #406 - existing CharacterFeature records
        // created before the fix have null feature_id, so we need to fall back
        // to looking up the feat by slug.
        $feat = Feat::factory()->create([
            'slug' => 'actor',
            'full_slug' => 'phb:actor',
            'name' => 'Actor',
        ]);

        $chaAbility = AbilityScore::where('code', 'CHA')->first();

        Modifier::create([
            'reference_type' => Feat::class,
            'reference_id' => $feat->id,
            'modifier_category' => 'ability_score',
            'ability_score_id' => $chaAbility->id,
            'value' => '1',
            'is_choice' => false,
        ]);

        $character = Character::factory()->create();

        // Manually create CharacterFeature WITHOUT feature_id (simulates legacy data)
        CharacterFeature::create([
            'character_id' => $character->id,
            'feature_type' => Feat::class,
            'feature_id' => null, // This is the bug - missing feature_id
            'feature_slug' => $feat->full_slug,
            'source' => 'race',
            'level_acquired' => 1,
        ]);

        $result = $this->service->getBonuses($character);

        // Should still return the feat bonus by looking up via slug
        $this->assertCount(1, $result['bonuses']);
        $bonus = $result['bonuses']->first();
        $this->assertEquals('feat', $bonus['source_type']);
        $this->assertEquals('Actor', $bonus['source_name']);
        $this->assertEquals('CHA', $bonus['ability_code']);
        $this->assertEquals(1, $bonus['value']);

        $this->assertEquals(1, $result['totals']['CHA']);
    }
}
