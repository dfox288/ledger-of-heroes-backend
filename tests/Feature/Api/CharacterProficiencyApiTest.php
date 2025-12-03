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

        CharacterProficiency::create([
            'character_id' => $character->id,
            'proficiency_type_id' => $this->lightArmor->id,
            'source' => 'class',
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/proficiencies");

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.source', 'class')
            ->assertJsonPath('data.0.proficiency_type.name', 'Light Armor');
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
    // POST /characters/{id}/proficiencies/populate
    // =============================

    #[Test]
    public function it_populates_fixed_proficiencies_from_class(): void
    {
        $character = Character::factory()
            ->withClass($this->fighterClass)
            ->create();

        $response = $this->postJson("/api/v1/characters/{$character->id}/proficiencies/populate");

        $response->assertOk()
            ->assertJsonPath('message', 'Proficiencies populated successfully');

        // Should have 2 fixed armor proficiencies (not skill choices)
        $this->assertCount(2, $response->json('data'));
    }

    #[Test]
    public function it_does_not_auto_populate_choice_proficiencies(): void
    {
        $character = Character::factory()
            ->withClass($this->fighterClass)
            ->create();

        $this->postJson("/api/v1/characters/{$character->id}/proficiencies/populate");

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

        $this->postJson("/api/v1/characters/{$character->id}/proficiencies/populate");
        $response = $this->postJson("/api/v1/characters/{$character->id}/proficiencies/populate");

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
    public function it_excludes_already_chosen_skills_from_pending(): void
    {
        $character = Character::factory()
            ->withClass($this->fighterClass)
            ->create();

        // Character already chose athletics
        CharacterProficiency::create([
            'character_id' => $character->id,
            'skill_id' => $this->athletics->id,
            'source' => 'class',
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/proficiency-choices");

        // Should have 1 remaining since 1 of 2 is fulfilled
        $this->assertEquals(1, $response->json('data.class.skill_choice_1.remaining'));
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
}
