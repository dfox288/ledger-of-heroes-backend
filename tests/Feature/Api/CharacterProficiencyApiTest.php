<?php

namespace Tests\Feature\Api;

use App\Models\AbilityScore;
use App\Models\Background;
use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\CharacterProficiency;
use App\Models\ProficiencyType;
use App\Models\Race;
use App\Models\Skill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CharacterProficiencyApiTest extends TestCase
{
    use RefreshDatabase;

    private CharacterClass $fighterClass;

    private Race $elfRace;

    private Background $soldierBackground;

    private ProficiencyType $lightArmor;

    private ProficiencyType $heavyArmor;

    private Skill $athletics;

    private Skill $acrobatics;

    private Skill $perception;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createFixtures();
    }

    private function createFixtures(): void
    {
        // Create ability score for skills
        $strength = AbilityScore::firstOrCreate(
            ['code' => 'STR'],
            ['name' => 'Strength', 'slug' => 'strength']
        );

        $dexterity = AbilityScore::firstOrCreate(
            ['code' => 'DEX'],
            ['name' => 'Dexterity', 'slug' => 'dexterity']
        );

        $wisdom = AbilityScore::firstOrCreate(
            ['code' => 'WIS'],
            ['name' => 'Wisdom', 'slug' => 'wisdom']
        );

        // Create skills with unique names to avoid constraint violations
        $uniqueId = uniqid();
        $this->athletics = Skill::create([
            'name' => 'Athletics '.$uniqueId,
            'slug' => 'athletics-'.$uniqueId,
            'ability_score_id' => $strength->id,
        ]);

        $this->acrobatics = Skill::create([
            'name' => 'Acrobatics '.$uniqueId,
            'slug' => 'acrobatics-'.$uniqueId,
            'ability_score_id' => $dexterity->id,
        ]);

        $this->perception = Skill::create([
            'name' => 'Perception '.$uniqueId,
            'slug' => 'perception-'.$uniqueId,
            'ability_score_id' => $wisdom->id,
        ]);

        // Create proficiency types
        $this->lightArmor = ProficiencyType::create([
            'name' => 'Light Armor',
            'slug' => 'light-armor-'.uniqid(),
            'category' => 'armor',
        ]);

        $this->heavyArmor = ProficiencyType::create([
            'name' => 'Heavy Armor',
            'slug' => 'heavy-armor-'.uniqid(),
            'category' => 'armor',
        ]);

        // Create class with proficiencies
        $this->fighterClass = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'slug' => 'fighter-'.uniqid(),
        ]);

        // Fixed proficiencies
        $this->fighterClass->proficiencies()->createMany([
            ['proficiency_type' => 'armor', 'proficiency_type_id' => $this->lightArmor->id, 'is_choice' => false],
            ['proficiency_type' => 'armor', 'proficiency_type_id' => $this->heavyArmor->id, 'is_choice' => false],
        ]);

        // Skill choices
        $this->fighterClass->proficiencies()->createMany([
            ['proficiency_type' => 'skill', 'skill_id' => $this->athletics->id, 'is_choice' => true, 'choice_group' => 'skill_choice_1', 'quantity' => 2],
            ['proficiency_type' => 'skill', 'skill_id' => $this->acrobatics->id, 'is_choice' => true, 'choice_group' => 'skill_choice_1'],
            ['proficiency_type' => 'skill', 'skill_id' => $this->perception->id, 'is_choice' => true, 'choice_group' => 'skill_choice_1'],
        ]);

        // Create race
        $this->elfRace = Race::factory()->create([
            'name' => 'Elf',
            'slug' => 'elf-'.uniqid(),
        ]);

        // Create background
        $this->soldierBackground = Background::factory()->create([
            'name' => 'Soldier',
            'slug' => 'soldier-'.uniqid(),
        ]);
    }

    // =============================
    // GET /characters/{id}/proficiencies
    // =============================

    #[Test]
    public function it_lists_character_proficiencies(): void
    {
        $character = Character::factory()
            ->withClass($this->fighterClass)
            ->create();

        // Create a stored proficiency
        CharacterProficiency::create([
            'character_id' => $character->id,
            'proficiency_type_id' => $this->lightArmor->id,
            'source' => 'class',
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/proficiencies");

        $response->assertOk();

        // Should have 2 proficiencies: 1 stored (light armor) + 1 granted (heavy armor)
        // The class grants both light and heavy armor, but light armor was already stored
        // so it should appear once (deduplicated)
        $this->assertCount(2, $response->json('data'));

        // Verify the stored one has an ID and the granted one has null ID
        $data = collect($response->json('data'));
        $storedProf = $data->firstWhere('proficiency_type.id', $this->lightArmor->id);
        $grantedProf = $data->firstWhere('proficiency_type.id', $this->heavyArmor->id);

        $this->assertNotNull($storedProf['id']); // Stored has ID
        $this->assertNull($grantedProf['id']); // Granted has null ID
    }

    #[Test]
    public function it_returns_empty_array_for_character_with_no_proficiencies(): void
    {
        $character = Character::factory()->create();

        $response = $this->getJson("/api/v1/characters/{$character->id}/proficiencies");

        $response->assertOk()
            ->assertJsonCount(0, 'data');
    }

    #[Test]
    public function it_includes_skill_details_in_proficiency_response(): void
    {
        $character = Character::factory()->create();

        CharacterProficiency::create([
            'character_id' => $character->id,
            'skill_id' => $this->athletics->id,
            'source' => 'class',
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/proficiencies");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'source',
                        'expertise',
                        'skill' => [
                            'id',
                            'name',
                            'slug',
                            'ability_code',
                        ],
                    ],
                ],
            ]);
    }

    // =============================
    // POST /characters/{id}/proficiencies/sync
    // =============================

    #[Test]
    public function it_populates_fixed_proficiencies_from_class(): void
    {
        $character = Character::factory()
            ->withClass($this->fighterClass)
            ->create();

        $response = $this->postJson("/api/v1/characters/{$character->id}/proficiencies/sync");

        $response->assertOk()
            ->assertJsonPath('message', 'Proficiencies synced successfully');

        // Should have 2 fixed armor proficiencies (not skill choices)
        // The sync stores them in the DB, but the response now includes all proficiencies
        // Both should appear with IDs since they were synced
        $this->assertCount(2, $response->json('data'));
    }

    #[Test]
    public function it_does_not_auto_populate_choice_proficiencies(): void
    {
        $character = Character::factory()
            ->withClass($this->fighterClass)
            ->create();

        $this->postJson("/api/v1/characters/{$character->id}/proficiencies/sync");

        // Verify no skill proficiencies were created (those are choices)
        $character->refresh();
        $skillProficiencies = $character->proficiencies->whereNotNull('skill_id');
        $this->assertCount(0, $skillProficiencies);
    }

    #[Test]
    public function it_does_not_duplicate_proficiencies_on_repeated_calls(): void
    {
        $character = Character::factory()
            ->withClass($this->fighterClass)
            ->create();

        $this->postJson("/api/v1/characters/{$character->id}/proficiencies/sync");
        $response = $this->postJson("/api/v1/characters/{$character->id}/proficiencies/sync");

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    // =============================
    // GET /characters/{id}/proficiency-choices
    // =============================

    #[Test]
    public function it_returns_pending_proficiency_choices(): void
    {
        $character = Character::factory()
            ->withClass($this->fighterClass)
            ->create();

        $response = $this->getJson("/api/v1/characters/{$character->id}/proficiency-choices");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'class' => [
                        'skill_choice_1' => [
                            'quantity',
                            'remaining',
                            'options',
                        ],
                    ],
                ],
            ]);

        $this->assertEquals(2, $response->json('data.class.skill_choice_1.quantity'));
        $this->assertEquals(2, $response->json('data.class.skill_choice_1.remaining'));
        $this->assertCount(3, $response->json('data.class.skill_choice_1.options'));
    }

    #[Test]
    public function it_returns_all_options_with_selected_ids_when_choices_made(): void
    {
        $character = Character::factory()
            ->withClass($this->fighterClass)
            ->create();

        // Character already chose athletics and acrobatics
        CharacterProficiency::create([
            'character_id' => $character->id,
            'skill_id' => $this->athletics->id,
            'source' => 'class',
            'choice_group' => 'skill_choice_1',
        ]);
        CharacterProficiency::create([
            'character_id' => $character->id,
            'skill_id' => $this->acrobatics->id,
            'source' => 'class',
            'choice_group' => 'skill_choice_1',
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/proficiency-choices");

        $response->assertOk();

        $choiceData = $response->json('data.class.skill_choice_1');

        // Should have 0 remaining since all choices made
        $this->assertEquals(0, $choiceData['remaining']);

        // Should still return ALL 3 options (not empty)
        $this->assertCount(3, $choiceData['options']);

        // Should include selected_skills array with the chosen skill IDs
        $this->assertArrayHasKey('selected_skills', $choiceData);
        $this->assertContains($this->athletics->id, $choiceData['selected_skills']);
        $this->assertContains($this->acrobatics->id, $choiceData['selected_skills']);
        $this->assertCount(2, $choiceData['selected_skills']);

        // selected_proficiency_types should be empty for skill-only choices
        $this->assertArrayHasKey('selected_proficiency_types', $choiceData);
        $this->assertEmpty($choiceData['selected_proficiency_types']);
    }

    #[Test]
    public function it_returns_partial_selection_with_remaining_count(): void
    {
        $character = Character::factory()
            ->withClass($this->fighterClass)
            ->create();

        // Character chose only 1 of 2 required
        CharacterProficiency::create([
            'character_id' => $character->id,
            'skill_id' => $this->athletics->id,
            'source' => 'class',
            'choice_group' => 'skill_choice_1',
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/proficiency-choices");

        $choiceData = $response->json('data.class.skill_choice_1');

        // Should have 1 remaining since 1 of 2 is fulfilled
        $this->assertEquals(1, $choiceData['remaining']);

        // Should still return ALL 3 options
        $this->assertCount(3, $choiceData['options']);

        // selected_skills should contain only the one chosen skill
        $this->assertCount(1, $choiceData['selected_skills']);
        $this->assertContains($this->athletics->id, $choiceData['selected_skills']);

        // selected_proficiency_types should be empty
        $this->assertEmpty($choiceData['selected_proficiency_types']);
    }

    #[Test]
    public function it_separates_skill_and_proficiency_type_selections(): void
    {
        // Create a race with both skill and tool proficiency choices
        $toolProficiency = ProficiencyType::create([
            'name' => 'Thieves Tools',
            'slug' => 'thieves-tools-'.uniqid(),
            'category' => 'tool',
        ]);

        $raceWithMixedChoices = Race::factory()->create([
            'name' => 'Half-Elf',
            'slug' => 'half-elf-'.uniqid(),
        ]);

        // Add skill choice option
        $raceWithMixedChoices->proficiencies()->create([
            'proficiency_type' => 'skill',
            'skill_id' => $this->perception->id,
            'is_choice' => true,
            'choice_group' => 'mixed_choice_1',
            'quantity' => 2,
        ]);

        // Add tool proficiency choice option
        $raceWithMixedChoices->proficiencies()->create([
            'proficiency_type' => 'tool',
            'proficiency_type_id' => $toolProficiency->id,
            'is_choice' => true,
            'choice_group' => 'mixed_choice_1',
        ]);

        $character = Character::factory()
            ->withRace($raceWithMixedChoices)
            ->create();

        // Character chose one skill and one tool proficiency
        CharacterProficiency::create([
            'character_id' => $character->id,
            'skill_id' => $this->perception->id,
            'source' => 'race',
            'choice_group' => 'mixed_choice_1',
        ]);
        CharacterProficiency::create([
            'character_id' => $character->id,
            'proficiency_type_id' => $toolProficiency->id,
            'source' => 'race',
            'choice_group' => 'mixed_choice_1',
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/proficiency-choices");

        $response->assertOk();

        $choiceData = $response->json('data.race.mixed_choice_1');

        // Should have 0 remaining
        $this->assertEquals(0, $choiceData['remaining']);

        // Should return all 2 options
        $this->assertCount(2, $choiceData['options']);

        // Skill selections should be in selected_skills
        $this->assertCount(1, $choiceData['selected_skills']);
        $this->assertContains($this->perception->id, $choiceData['selected_skills']);

        // Tool proficiency selections should be in selected_proficiency_types
        $this->assertCount(1, $choiceData['selected_proficiency_types']);
        $this->assertContains($toolProficiency->id, $choiceData['selected_proficiency_types']);
    }

    // =============================
    // POST /characters/{id}/proficiency-choices
    // =============================

    #[Test]
    public function it_accepts_valid_skill_choices(): void
    {
        $character = Character::factory()
            ->withClass($this->fighterClass)
            ->create();

        $response = $this->postJson("/api/v1/characters/{$character->id}/proficiency-choices", [
            'source' => 'class',
            'choice_group' => 'skill_choice_1',
            'skill_ids' => [$this->athletics->id, $this->acrobatics->id],
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Choice saved successfully');

        // Verify proficiencies were created
        $character->refresh();
        $this->assertCount(2, $character->proficiencies);
        $this->assertTrue($character->proficiencies->contains('skill_id', $this->athletics->id));
        $this->assertTrue($character->proficiencies->contains('skill_id', $this->acrobatics->id));
    }

    #[Test]
    public function it_rejects_wrong_quantity_of_skill_choices(): void
    {
        $character = Character::factory()
            ->withClass($this->fighterClass)
            ->create();

        $response = $this->postJson("/api/v1/characters/{$character->id}/proficiency-choices", [
            'source' => 'class',
            'choice_group' => 'skill_choice_1',
            'skill_ids' => [$this->athletics->id], // Only 1 when 2 required
        ]);

        $response->assertStatus(422);
    }

    #[Test]
    public function it_replaces_existing_choices_when_resubmitting(): void
    {
        $character = Character::factory()
            ->withClass($this->fighterClass)
            ->create();

        // First submission: athletics + acrobatics
        $this->postJson("/api/v1/characters/{$character->id}/proficiency-choices", [
            'source' => 'class',
            'choice_group' => 'skill_choice_1',
            'skill_ids' => [$this->athletics->id, $this->acrobatics->id],
        ])->assertOk();

        $character->refresh();
        $this->assertCount(2, $character->proficiencies);

        // Second submission: athletics + perception (change of mind)
        $response = $this->postJson("/api/v1/characters/{$character->id}/proficiency-choices", [
            'source' => 'class',
            'choice_group' => 'skill_choice_1',
            'skill_ids' => [$this->athletics->id, $this->perception->id],
        ]);

        $response->assertOk();

        // Should still have exactly 2 proficiencies (replaced, not added)
        $character->refresh();
        $this->assertCount(2, $character->proficiencies);
        $this->assertTrue($character->proficiencies->contains('skill_id', $this->athletics->id));
        $this->assertTrue($character->proficiencies->contains('skill_id', $this->perception->id));
        // Acrobatics should be gone (replaced)
        $this->assertFalse($character->proficiencies->contains('skill_id', $this->acrobatics->id));
    }

    #[Test]
    public function it_rejects_invalid_skill_choices(): void
    {
        $character = Character::factory()
            ->withClass($this->fighterClass)
            ->create();

        // Create a skill that's not in the fighter's options
        $uniqueId = uniqid();
        $stealth = Skill::create([
            'name' => 'Stealth '.$uniqueId,
            'slug' => 'stealth-'.$uniqueId,
            'ability_score_id' => AbilityScore::where('code', 'DEX')->first()->id,
        ]);

        $response = $this->postJson("/api/v1/characters/{$character->id}/proficiency-choices", [
            'source' => 'class',
            'choice_group' => 'skill_choice_1',
            'skill_ids' => [$this->athletics->id, $stealth->id], // Stealth not valid for fighter
        ]);

        $response->assertStatus(422);
    }

    #[Test]
    public function it_validates_required_fields_for_choice(): void
    {
        $character = Character::factory()->create();

        $response = $this->postJson("/api/v1/characters/{$character->id}/proficiency-choices", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['source', 'choice_group', 'skill_ids']);
    }

    #[Test]
    public function it_validates_source_is_valid(): void
    {
        $character = Character::factory()->create();

        $response = $this->postJson("/api/v1/characters/{$character->id}/proficiency-choices", [
            'source' => 'invalid',
            'choice_group' => 'skill_choice_1',
            'skill_ids' => [1],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['source']);
    }

    // =============================
    // Error Handling
    // =============================

    #[Test]
    public function it_returns_404_for_nonexistent_character(): void
    {
        $response = $this->getJson('/api/v1/characters/99999/proficiencies');

        $response->assertNotFound();
    }

    // =============================
    // Granted Proficiencies (Issue #218)
    // =============================

    #[Test]
    public function it_includes_granted_armor_proficiencies_from_class(): void
    {
        $character = Character::factory()
            ->withClass($this->fighterClass)
            ->create();

        // Don't sync - we want to test that granted proficiencies appear without sync
        $response = $this->getJson("/api/v1/characters/{$character->id}/proficiencies");

        $response->assertOk();

        // Should include the fixed armor proficiencies from class even without sync
        $data = $response->json('data');
        $armorProficiencies = collect($data)->filter(fn ($p) => isset($p['proficiency_type']) && $p['proficiency_type']['category'] === 'armor');

        $this->assertCount(2, $armorProficiencies);
        $this->assertTrue($armorProficiencies->contains(fn ($p) => $p['proficiency_type']['name'] === 'Light Armor'));
        $this->assertTrue($armorProficiencies->contains(fn ($p) => $p['proficiency_type']['name'] === 'Heavy Armor'));
    }

    #[Test]
    public function it_includes_granted_proficiencies_from_race(): void
    {
        // Add a fixed proficiency to the race
        $this->elfRace->proficiencies()->create([
            'proficiency_type' => 'skill',
            'skill_id' => $this->perception->id,
            'is_choice' => false,
        ]);

        $character = Character::factory()
            ->withRace($this->elfRace)
            ->create();

        $response = $this->getJson("/api/v1/characters/{$character->id}/proficiencies");

        $response->assertOk();

        $data = $response->json('data');
        $skillProficiencies = collect($data)->filter(fn ($p) => isset($p['skill']));

        $this->assertCount(1, $skillProficiencies);
        $this->assertTrue($skillProficiencies->contains(fn ($p) => $p['skill']['id'] === $this->perception->id));
        $this->assertTrue($skillProficiencies->contains(fn ($p) => $p['source'] === 'race'));
    }

    #[Test]
    public function it_includes_granted_proficiencies_from_background(): void
    {
        // Add a fixed skill proficiency to the background
        $this->soldierBackground->proficiencies()->create([
            'proficiency_type' => 'skill',
            'skill_id' => $this->athletics->id,
            'is_choice' => false,
        ]);

        $character = Character::factory()
            ->withBackground($this->soldierBackground)
            ->create();

        $response = $this->getJson("/api/v1/characters/{$character->id}/proficiencies");

        $response->assertOk();

        $data = $response->json('data');
        $skillProficiencies = collect($data)->filter(fn ($p) => isset($p['skill']));

        $this->assertCount(1, $skillProficiencies);
        $this->assertTrue($skillProficiencies->contains(fn ($p) => $p['skill']['id'] === $this->athletics->id));
        $this->assertTrue($skillProficiencies->contains(fn ($p) => $p['source'] === 'background'));
    }

    #[Test]
    public function it_marks_granted_proficiencies_with_null_id(): void
    {
        $character = Character::factory()
            ->withClass($this->fighterClass)
            ->create();

        $response = $this->getJson("/api/v1/characters/{$character->id}/proficiencies");

        $response->assertOk();

        $data = $response->json('data');
        $grantedProficiencies = collect($data)->filter(fn ($p) => isset($p['proficiency_type']));

        // Granted proficiencies should have null ID since they're not stored in character_proficiencies
        foreach ($grantedProficiencies as $proficiency) {
            $this->assertNull($proficiency['id']);
        }
    }

    #[Test]
    public function it_deduplicates_proficiencies_when_user_chose_same_as_granted(): void
    {
        // Class grants Light Armor
        $character = Character::factory()
            ->withClass($this->fighterClass)
            ->create();

        // User also has Light Armor stored (e.g., synced)
        CharacterProficiency::create([
            'character_id' => $character->id,
            'proficiency_type_id' => $this->lightArmor->id,
            'source' => 'class',
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/proficiencies");

        $response->assertOk();

        $data = $response->json('data');
        $lightArmorProficiencies = collect($data)->filter(
            fn ($p) => isset($p['proficiency_type']) && $p['proficiency_type']['id'] === $this->lightArmor->id
        );

        // Should only appear once, not twice
        $this->assertCount(1, $lightArmorProficiencies);
        // Should prefer the stored one (has an ID)
        $this->assertNotNull($lightArmorProficiencies->first()['id']);
    }

    #[Test]
    public function it_aggregates_proficiencies_from_all_sources(): void
    {
        // Add proficiencies to each source
        $this->elfRace->proficiencies()->create([
            'proficiency_type' => 'skill',
            'skill_id' => $this->perception->id,
            'is_choice' => false,
        ]);

        $this->soldierBackground->proficiencies()->create([
            'proficiency_type' => 'skill',
            'skill_id' => $this->acrobatics->id,
            'is_choice' => false,
        ]);

        $character = Character::factory()
            ->withClass($this->fighterClass) // grants light armor, heavy armor
            ->withRace($this->elfRace) // grants perception
            ->withBackground($this->soldierBackground) // grants acrobatics
            ->create();

        $response = $this->getJson("/api/v1/characters/{$character->id}/proficiencies");

        $response->assertOk();

        $data = $response->json('data');

        // Should have 4 proficiencies total: 2 armor + 2 skills
        $this->assertCount(4, $data);

        // Verify sources are correctly attributed
        $sources = collect($data)->pluck('source')->unique()->sort()->values();
        $this->assertEquals(['background', 'class', 'race'], $sources->toArray());
    }

    #[Test]
    public function it_does_not_include_choice_proficiencies_as_granted(): void
    {
        $character = Character::factory()
            ->withClass($this->fighterClass) // has skill choices
            ->create();

        $response = $this->getJson("/api/v1/characters/{$character->id}/proficiencies");

        $response->assertOk();

        $data = $response->json('data');

        // Should only have fixed proficiencies (armor), not skill choices
        $this->assertCount(2, $data);

        foreach ($data as $proficiency) {
            $this->assertArrayHasKey('proficiency_type', $proficiency);
        }
    }

    // =============================
    // Subcategory-Based Choices (Issue #168)
    // =============================

    #[Test]
    public function it_includes_proficiency_type_and_subcategory_for_subcategory_choices(): void
    {
        // Create a class with artisan tool choice (subcategory-based, no specific options)
        $artificerClass = CharacterClass::factory()->create([
            'name' => 'Artificer',
            'slug' => 'artificer-'.uniqid(),
        ]);

        // Subcategory-based choice: "one type of artisan's tools of your choice"
        // This has empty options - the frontend needs to look up options from proficiency_types
        $artificerClass->proficiencies()->create([
            'proficiency_type' => 'tool',
            'proficiency_subcategory' => 'artisan',
            'proficiency_type_id' => null, // No specific type - it's a subcategory choice
            'is_choice' => true,
            'choice_group' => 'tool_choice_1',
            'quantity' => 1,
        ]);

        $character = Character::factory()
            ->withClass($artificerClass)
            ->create();

        $response = $this->getJson("/api/v1/characters/{$character->id}/proficiency-choices");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'class' => [
                        'tool_choice_1' => [
                            'proficiency_type',
                            'proficiency_subcategory',
                            'quantity',
                            'remaining',
                            'options',
                        ],
                    ],
                ],
            ]);

        $choiceData = $response->json('data.class.tool_choice_1');

        // Should include proficiency_type and proficiency_subcategory for frontend lookup
        $this->assertEquals('tool', $choiceData['proficiency_type']);
        $this->assertEquals('artisan', $choiceData['proficiency_subcategory']);

        // Options should be empty since this is a subcategory-based choice
        $this->assertEmpty($choiceData['options']);
    }

    #[Test]
    public function it_returns_null_subcategory_for_specific_option_choices(): void
    {
        // For normal choices with specific options, proficiency_subcategory should be null
        $character = Character::factory()
            ->withClass($this->fighterClass)
            ->create();

        $response = $this->getJson("/api/v1/characters/{$character->id}/proficiency-choices");

        $response->assertOk();

        $choiceData = $response->json('data.class.skill_choice_1');

        // proficiency_type should be 'skill'
        $this->assertEquals('skill', $choiceData['proficiency_type']);

        // proficiency_subcategory should be null for specific option choices
        $this->assertNull($choiceData['proficiency_subcategory']);

        // Options should NOT be empty - they have specific skills
        $this->assertNotEmpty($choiceData['options']);
    }
}
