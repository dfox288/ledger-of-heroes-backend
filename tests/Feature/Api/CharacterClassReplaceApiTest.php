<?php

namespace Tests\Feature\Api;

use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\CharacterClassPivot;
use App\Models\CharacterProficiency;
use App\Models\ProficiencyType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CharacterClassReplaceApiTest extends TestCase
{
    use RefreshDatabase;

    private CharacterClass $fighterClass;

    private CharacterClass $wizardClass;

    private CharacterClass $rogueClass;

    private ProficiencyType $lightArmor;

    private ProficiencyType $heavyArmor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createFixtures();
    }

    private function createFixtures(): void
    {
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

        // Create classes
        $this->fighterClass = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'slug' => 'fighter-'.uniqid(),
        ]);
        $this->fighterClass->proficiencies()->createMany([
            ['proficiency_type' => 'armor', 'proficiency_type_id' => $this->lightArmor->id, 'is_choice' => false],
            ['proficiency_type' => 'armor', 'proficiency_type_id' => $this->heavyArmor->id, 'is_choice' => false],
        ]);

        $this->wizardClass = CharacterClass::factory()->create([
            'name' => 'Wizard',
            'slug' => 'wizard-'.uniqid(),
        ]);

        $this->rogueClass = CharacterClass::factory()->create([
            'name' => 'Rogue',
            'slug' => 'rogue-'.uniqid(),
        ]);
        $this->rogueClass->proficiencies()->create([
            'proficiency_type' => 'armor',
            'proficiency_type_id' => $this->lightArmor->id,
            'is_choice' => false,
        ]);
    }

    // =============================
    // PUT /characters/{id}/classes/{classIdOrSlug}
    // =============================

    #[Test]
    public function it_replaces_class_for_level_1_character_by_id(): void
    {
        $character = Character::factory()
            ->withClass($this->fighterClass)
            ->create();

        $response = $this->putJson(
            "/api/v1/characters/{$character->id}/classes/{$this->fighterClass->id}",
            ['class_id' => $this->wizardClass->id]
        );

        $response->assertOk()
            ->assertJsonPath('data.class.id', $this->wizardClass->id)
            ->assertJsonPath('data.class.name', 'Wizard')
            ->assertJsonPath('data.level', 1)
            ->assertJsonPath('data.is_primary', true);

        // Verify old class is gone
        $character->refresh();
        $this->assertFalse($character->characterClasses->contains('class_id', $this->fighterClass->id));
        $this->assertTrue($character->characterClasses->contains('class_id', $this->wizardClass->id));
    }

    #[Test]
    public function it_replaces_class_for_level_1_character_by_slug(): void
    {
        $character = Character::factory()
            ->withClass($this->fighterClass)
            ->create();

        $response = $this->putJson(
            "/api/v1/characters/{$character->id}/classes/{$this->fighterClass->slug}",
            ['class_id' => $this->rogueClass->id]
        );

        $response->assertOk()
            ->assertJsonPath('data.class.id', $this->rogueClass->id);
    }

    #[Test]
    public function it_preserves_is_primary_flag_when_replacing(): void
    {
        $character = Character::factory()
            ->withClass($this->fighterClass)
            ->create();

        // Verify it's primary before
        $pivot = $character->characterClasses->first();
        $this->assertTrue($pivot->is_primary);

        $response = $this->putJson(
            "/api/v1/characters/{$character->id}/classes/{$this->fighterClass->id}",
            ['class_id' => $this->wizardClass->id]
        );

        $response->assertOk()
            ->assertJsonPath('data.is_primary', true);
    }

    #[Test]
    public function it_preserves_order_when_replacing(): void
    {
        $character = Character::factory()
            ->withClass($this->fighterClass)
            ->create();

        $originalOrder = $character->characterClasses->first()->order;

        $response = $this->putJson(
            "/api/v1/characters/{$character->id}/classes/{$this->fighterClass->id}",
            ['class_id' => $this->wizardClass->id]
        );

        $response->assertOk()
            ->assertJsonPath('data.order', $originalOrder);
    }

    #[Test]
    public function it_clears_subclass_when_replacing(): void
    {
        // Create a subclass for fighter
        $subclass = CharacterClass::factory()->create([
            'name' => 'Champion',
            'slug' => 'champion-'.uniqid(),
            'parent_class_id' => $this->fighterClass->id,
        ]);

        $character = Character::factory()->create();
        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_id' => $this->fighterClass->id,
            'subclass_id' => $subclass->id,
            'level' => 1,
            'is_primary' => true,
            'order' => 1,
            'hit_dice_spent' => 0,
        ]);

        $response = $this->putJson(
            "/api/v1/characters/{$character->id}/classes/{$this->fighterClass->id}",
            ['class_id' => $this->wizardClass->id]
        );

        $response->assertOk()
            ->assertJsonPath('data.subclass', null);
    }

    #[Test]
    public function it_resets_hit_dice_spent_when_replacing(): void
    {
        $character = Character::factory()->create();
        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_id' => $this->fighterClass->id,
            'level' => 1,
            'is_primary' => true,
            'order' => 1,
            'hit_dice_spent' => 1, // Some hit dice used
        ]);

        $response = $this->putJson(
            "/api/v1/characters/{$character->id}/classes/{$this->fighterClass->id}",
            ['class_id' => $this->wizardClass->id]
        );

        $response->assertOk()
            ->assertJsonPath('data.hit_dice.spent', 0);
    }

    // =============================
    // Validation Tests
    // =============================

    #[Test]
    public function it_fails_if_character_level_is_above_1(): void
    {
        $character = Character::factory()->create();
        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_id' => $this->fighterClass->id,
            'level' => 2, // Level 2
            'is_primary' => true,
            'order' => 1,
            'hit_dice_spent' => 0,
        ]);

        $response = $this->putJson(
            "/api/v1/characters/{$character->id}/classes/{$this->fighterClass->id}",
            ['class_id' => $this->wizardClass->id]
        );

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Can only replace class at level 1');
    }

    #[Test]
    public function it_fails_if_character_has_multiple_classes(): void
    {
        $character = Character::factory()
            ->withClass($this->fighterClass)
            ->withClass($this->rogueClass)
            ->create();

        $response = $this->putJson(
            "/api/v1/characters/{$character->id}/classes/{$this->fighterClass->id}",
            ['class_id' => $this->wizardClass->id]
        );

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Cannot replace class when character has multiple classes');
    }

    #[Test]
    public function it_fails_if_source_class_not_found_on_character(): void
    {
        $character = Character::factory()
            ->withClass($this->fighterClass)
            ->create();

        $response = $this->putJson(
            "/api/v1/characters/{$character->id}/classes/{$this->wizardClass->id}",
            ['class_id' => $this->rogueClass->id]
        );

        $response->assertStatus(404)
            ->assertJsonPath('message', 'Class not found on character');
    }

    #[Test]
    public function it_fails_if_target_class_does_not_exist(): void
    {
        $character = Character::factory()
            ->withClass($this->fighterClass)
            ->create();

        $response = $this->putJson(
            "/api/v1/characters/{$character->id}/classes/{$this->fighterClass->id}",
            ['class_id' => 99999]
        );

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['class_id']);
    }

    #[Test]
    public function it_fails_if_target_class_is_same_as_source(): void
    {
        $character = Character::factory()
            ->withClass($this->fighterClass)
            ->create();

        $response = $this->putJson(
            "/api/v1/characters/{$character->id}/classes/{$this->fighterClass->id}",
            ['class_id' => $this->fighterClass->id]
        );

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Cannot replace class with the same class');
    }

    #[Test]
    public function it_fails_if_target_is_a_subclass(): void
    {
        $subclass = CharacterClass::factory()->create([
            'name' => 'Champion',
            'slug' => 'champion-'.uniqid(),
            'parent_class_id' => $this->fighterClass->id,
        ]);

        $character = Character::factory()
            ->withClass($this->fighterClass)
            ->create();

        $response = $this->putJson(
            "/api/v1/characters/{$character->id}/classes/{$this->fighterClass->id}",
            ['class_id' => $subclass->id]
        );

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Cannot use a subclass as the replacement class');
    }

    #[Test]
    public function it_validates_class_id_is_required(): void
    {
        $character = Character::factory()
            ->withClass($this->fighterClass)
            ->create();

        $response = $this->putJson(
            "/api/v1/characters/{$character->id}/classes/{$this->fighterClass->id}",
            []
        );

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['class_id']);
    }

    // =============================
    // Force Flag Tests
    // =============================

    #[Test]
    public function it_accepts_force_flag(): void
    {
        $character = Character::factory()
            ->withClass($this->fighterClass)
            ->create();

        $response = $this->putJson(
            "/api/v1/characters/{$character->id}/classes/{$this->fighterClass->id}",
            ['class_id' => $this->wizardClass->id, 'force' => true]
        );

        $response->assertOk();
    }

    // =============================
    // Cascading Effects Tests
    // =============================

    #[Test]
    public function it_clears_old_class_proficiencies_when_replacing(): void
    {
        $character = Character::factory()
            ->withClass($this->fighterClass)
            ->create();

        // Sync proficiencies from fighter (light armor, heavy armor)
        CharacterProficiency::create([
            'character_id' => $character->id,
            'proficiency_type_id' => $this->lightArmor->id,
            'source' => 'class',
        ]);
        CharacterProficiency::create([
            'character_id' => $character->id,
            'proficiency_type_id' => $this->heavyArmor->id,
            'source' => 'class',
        ]);

        $this->assertCount(2, $character->proficiencies);

        // Replace with wizard (no armor proficiencies)
        $response = $this->putJson(
            "/api/v1/characters/{$character->id}/classes/{$this->fighterClass->id}",
            ['class_id' => $this->wizardClass->id]
        );

        $response->assertOk();

        // Old class proficiencies should be cleared
        $character->refresh();
        $classProficiencies = $character->proficiencies()->where('source', 'class')->get();
        $this->assertCount(0, $classProficiencies);
    }

    // =============================
    // Error Handling
    // =============================

    #[Test]
    public function it_returns_404_for_nonexistent_character(): void
    {
        $response = $this->putJson(
            '/api/v1/characters/99999/classes/'.$this->fighterClass->id,
            ['class_id' => $this->wizardClass->id]
        );

        $response->assertNotFound();
    }

    #[Test]
    public function it_returns_404_for_nonexistent_source_class(): void
    {
        $character = Character::factory()
            ->withClass($this->fighterClass)
            ->create();

        $response = $this->putJson(
            "/api/v1/characters/{$character->id}/classes/99999",
            ['class_id' => $this->wizardClass->id]
        );

        $response->assertNotFound();
    }

    #[Test]
    public function it_returns_404_for_invalid_source_class_slug(): void
    {
        $character = Character::factory()
            ->withClass($this->fighterClass)
            ->create();

        $response = $this->putJson(
            "/api/v1/characters/{$character->id}/classes/nonexistent-class",
            ['class_id' => $this->wizardClass->id]
        );

        $response->assertNotFound();
    }
}
