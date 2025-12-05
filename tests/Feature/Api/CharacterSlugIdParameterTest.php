<?php

namespace Tests\Feature\Api;

use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\OptionalFeature;
use App\Models\Spell;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CharacterSlugIdParameterTest extends TestCase
{
    use RefreshDatabase;

    private Character $character;

    private Spell $spell;

    private CharacterClass $class;

    private OptionalFeature $optionalFeature;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test character
        $this->character = Character::factory()->create();

        // Create test entities with slugs
        $this->spell = Spell::factory()->create([
            'name' => 'Test Fireball',
            'slug' => 'test-fireball',
        ]);

        $this->class = CharacterClass::factory()->create([
            'name' => 'Test Fighter',
            'slug' => 'test-fighter',
        ]);

        $this->optionalFeature = OptionalFeature::factory()->create([
            'name' => 'Test Invocation',
            'slug' => 'test-invocation',
        ]);
    }

    // ========================================
    // CharacterSpellController Tests
    // ========================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_deletes_spell_by_id()
    {
        // Add spell to character
        \App\Models\CharacterSpell::create([
            'character_id' => $this->character->id,
            'spell_id' => $this->spell->id,
            'preparation_status' => 'known',
            'source' => 'class',
        ]);

        $response = $this->deleteJson("/api/v1/characters/{$this->character->id}/spells/{$this->spell->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('character_spells', [
            'character_id' => $this->character->id,
            'spell_id' => $this->spell->id,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_deletes_spell_by_slug()
    {
        // Add spell to character
        \App\Models\CharacterSpell::create([
            'character_id' => $this->character->id,
            'spell_id' => $this->spell->id,
            'preparation_status' => 'known',
            'source' => 'class',
        ]);

        $response = $this->deleteJson("/api/v1/characters/{$this->character->id}/spells/{$this->spell->slug}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('character_spells', [
            'character_id' => $this->character->id,
            'spell_id' => $this->spell->id,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_404_for_invalid_spell_slug()
    {
        $response = $this->deleteJson("/api/v1/characters/{$this->character->id}/spells/invalid-slug");

        $response->assertNotFound();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_404_for_invalid_spell_id()
    {
        $response = $this->deleteJson("/api/v1/characters/{$this->character->id}/spells/99999");

        $response->assertNotFound();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_prepares_spell_by_id()
    {
        // Add spell to character (not prepared)
        \App\Models\CharacterSpell::create([
            'character_id' => $this->character->id,
            'spell_id' => $this->spell->id,
            'preparation_status' => 'known',
            'source' => 'class',
        ]);

        $response = $this->patchJson("/api/v1/characters/{$this->character->id}/spells/{$this->spell->id}/prepare");

        $response->assertOk();
        $response->assertJsonPath('data.preparation_status', 'prepared');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_prepares_spell_by_slug()
    {
        // Add spell to character (not prepared)
        \App\Models\CharacterSpell::create([
            'character_id' => $this->character->id,
            'spell_id' => $this->spell->id,
            'preparation_status' => 'known',
            'source' => 'class',
        ]);

        $response = $this->patchJson("/api/v1/characters/{$this->character->id}/spells/{$this->spell->slug}/prepare");

        $response->assertOk();
        $response->assertJsonPath('data.preparation_status', 'prepared');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_unprepares_spell_by_id()
    {
        // Add spell to character (prepared)
        \App\Models\CharacterSpell::create([
            'character_id' => $this->character->id,
            'spell_id' => $this->spell->id,
            'preparation_status' => 'prepared',
            'source' => 'class',
        ]);

        $response = $this->patchJson("/api/v1/characters/{$this->character->id}/spells/{$this->spell->id}/unprepare");

        $response->assertOk();
        $response->assertJsonPath('data.preparation_status', 'known');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_unprepares_spell_by_slug()
    {
        // Add spell to character (prepared)
        \App\Models\CharacterSpell::create([
            'character_id' => $this->character->id,
            'spell_id' => $this->spell->id,
            'preparation_status' => 'prepared',
            'source' => 'class',
        ]);

        $response = $this->patchJson("/api/v1/characters/{$this->character->id}/spells/{$this->spell->slug}/unprepare");

        $response->assertOk();
        $response->assertJsonPath('data.preparation_status', 'known');
    }

    // ========================================
    // CharacterClassController Tests
    // ========================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_deletes_character_class_by_id()
    {
        // Add two classes to character (need at least 2 to delete one)
        $secondClass = CharacterClass::factory()->create();
        $this->character->characterClasses()->create([
            'class_id' => $this->class->id,
            'level' => 3,
        ]);
        $this->character->characterClasses()->create([
            'class_id' => $secondClass->id,
            'level' => 2,
        ]);

        $response = $this->deleteJson("/api/v1/characters/{$this->character->id}/classes/{$this->class->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('character_classes', [
            'character_id' => $this->character->id,
            'class_id' => $this->class->id,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_deletes_character_class_by_slug()
    {
        // Add two classes to character (need at least 2 to delete one)
        $secondClass = CharacterClass::factory()->create();
        $this->character->characterClasses()->create([
            'class_id' => $this->class->id,
            'level' => 3,
        ]);
        $this->character->characterClasses()->create([
            'class_id' => $secondClass->id,
            'level' => 2,
        ]);

        $response = $this->deleteJson("/api/v1/characters/{$this->character->id}/classes/{$this->class->slug}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('character_classes', [
            'character_id' => $this->character->id,
            'class_id' => $this->class->id,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_404_for_invalid_class_slug()
    {
        $response = $this->deleteJson("/api/v1/characters/{$this->character->id}/classes/invalid-slug");

        $response->assertNotFound();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_404_for_invalid_class_id()
    {
        $response = $this->deleteJson("/api/v1/characters/{$this->character->id}/classes/99999");

        $response->assertNotFound();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_levels_up_class_by_id()
    {
        // Add class to character
        $this->character->characterClasses()->create([
            'class_id' => $this->class->id,
            'level' => 3,
        ]);

        $response = $this->postJson("/api/v1/characters/{$this->character->id}/classes/{$this->class->id}/level-up");

        $response->assertOk();
        $response->assertJsonPath('data.level', 4);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_levels_up_class_by_slug()
    {
        // Add class to character
        $this->character->characterClasses()->create([
            'class_id' => $this->class->id,
            'level' => 3,
        ]);

        $response = $this->postJson("/api/v1/characters/{$this->character->id}/classes/{$this->class->slug}/level-up");

        $response->assertOk();
        $response->assertJsonPath('data.level', 4);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_sets_subclass_by_id()
    {
        // Create a subclass for this class
        $subclass = CharacterClass::factory()->create([
            'parent_class_id' => $this->class->id,
            'name' => 'Test Battle Master',
            'slug' => 'test-battle-master',
        ]);

        // Add class to character at level 3
        $this->character->characterClasses()->create([
            'class_id' => $this->class->id,
            'level' => 3,
        ]);

        $response = $this->putJson(
            "/api/v1/characters/{$this->character->id}/classes/{$this->class->id}/subclass",
            ['subclass_id' => $subclass->id]
        );

        $response->assertOk();
        $response->assertJsonPath('data.subclass.id', $subclass->id);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_sets_subclass_by_slug()
    {
        // Create a subclass for this class
        $subclass = CharacterClass::factory()->create([
            'parent_class_id' => $this->class->id,
            'name' => 'Test Battle Master',
            'slug' => 'test-battle-master',
        ]);

        // Add class to character at level 3
        $this->character->characterClasses()->create([
            'class_id' => $this->class->id,
            'level' => 3,
        ]);

        $response = $this->putJson(
            "/api/v1/characters/{$this->character->id}/classes/{$this->class->slug}/subclass",
            ['subclass_id' => $subclass->id]
        );

        $response->assertOk();
        $response->assertJsonPath('data.subclass.id', $subclass->id);
    }

    // ========================================
    // FeatureSelectionController Tests
    // ========================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_deletes_feature_selection_by_id()
    {
        // Add feature selection to character
        $this->character->featureSelections()->create([
            'optional_feature_id' => $this->optionalFeature->id,
            'level_acquired' => 3,
        ]);

        $response = $this->deleteJson("/api/v1/characters/{$this->character->id}/feature-selections/{$this->optionalFeature->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('feature_selections', [
            'character_id' => $this->character->id,
            'optional_feature_id' => $this->optionalFeature->id,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_deletes_feature_selection_by_slug()
    {
        // Add feature selection to character
        $this->character->featureSelections()->create([
            'optional_feature_id' => $this->optionalFeature->id,
            'level_acquired' => 3,
        ]);

        $response = $this->deleteJson("/api/v1/characters/{$this->character->id}/feature-selections/{$this->optionalFeature->slug}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('feature_selections', [
            'character_id' => $this->character->id,
            'optional_feature_id' => $this->optionalFeature->id,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_404_for_invalid_feature_selection_slug()
    {
        $response = $this->deleteJson("/api/v1/characters/{$this->character->id}/feature-selections/invalid-slug");

        $response->assertNotFound();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_404_for_invalid_feature_selection_id()
    {
        $response = $this->deleteJson("/api/v1/characters/{$this->character->id}/feature-selections/99999");

        $response->assertNotFound();
    }
}
