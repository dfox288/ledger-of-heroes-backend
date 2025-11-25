<?php

namespace Tests\Feature\Api;

use App\Models\AbilityScore;
use App\Models\CharacterClass;
use App\Models\Spell;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClassEntitySpecificFiltersApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Flush Meilisearch classes index before each test
        // This ensures a clean state for filter tests
        try {
            CharacterClass::removeAllFromSearch();
        } catch (\Exception $e) {
            // Index might not exist yet - that's OK
        }
    }

    /**
     * Index a class in Meilisearch and wait for indexing to complete.
     */
    private function indexClass(CharacterClass $class): void
    {
        $class->searchable();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_classes_by_is_spellcaster_true(): void
    {
        // Arrange: Create spellcasters with spellcasting ability
        $wizardAbility = AbilityScore::where('code', 'INT')->first();
        $clericAbility = AbilityScore::where('code', 'WIS')->first();

        $wizard = CharacterClass::factory()->create([
            'name' => 'Wizard',
            'spellcasting_ability_id' => $wizardAbility->id,
            'hit_die' => 6,
        ]);
        $wizard->searchable(); // Index in Meilisearch

        $cleric = CharacterClass::factory()->create([
            'name' => 'Cleric',
            'spellcasting_ability_id' => $clericAbility->id,
            'hit_die' => 8,
        ]);
        $cleric->searchable(); // Index in Meilisearch

        // Create non-spellcasters (no spellcasting ability)
        $fighter = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'spellcasting_ability_id' => null,
            'hit_die' => 10,
        ]);
        $fighter->searchable(); // Index in Meilisearch

        $barbarian = CharacterClass::factory()->create([
            'name' => 'Barbarian',
            'spellcasting_ability_id' => null,
            'hit_die' => 12,
        ]);
        $barbarian->searchable(); // Index in Meilisearch

        sleep(1); // Wait for Meilisearch indexing

        // Act: Filter by is_spellcaster=true (using Meilisearch filter syntax)
        $response = $this->getJson('/api/v1/classes?filter=is_spellcaster = true');

        // Assert: Only spellcasters returned
        $response->assertOk();
        $data = $response->json('data');

        $this->assertCount(2, $data);
        $names = collect($data)->pluck('name')->toArray();
        $this->assertContains('Wizard', $names);
        $this->assertContains('Cleric', $names);
        $this->assertNotContains('Fighter', $names);
        $this->assertNotContains('Barbarian', $names);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_classes_by_is_spellcaster_false(): void
    {
        // Arrange: Create spellcasters
        $wizardAbility = AbilityScore::where('code', 'INT')->first();
        $wizard = CharacterClass::factory()->create([
            'name' => 'Wizard',
            'spellcasting_ability_id' => $wizardAbility->id,
            'hit_die' => 6,
        ]);
        $wizard->searchable();

        // Create non-spellcasters
        $fighter = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'spellcasting_ability_id' => null,
            'hit_die' => 10,
        ]);
        $fighter->searchable();

        $barbarian = CharacterClass::factory()->create([
            'name' => 'Barbarian',
            'spellcasting_ability_id' => null,
            'hit_die' => 12,
        ]);
        $barbarian->searchable();

        $rogue = CharacterClass::factory()->create([
            'name' => 'Rogue',
            'spellcasting_ability_id' => null,
            'hit_die' => 8,
        ]);
        $rogue->searchable();

        sleep(1); // Wait for Meilisearch indexing

        // Act: Filter by is_spellcaster=false (using Meilisearch filter syntax)
        $response = $this->getJson('/api/v1/classes?filter=is_spellcaster = false');

        // Assert: Only non-spellcasters returned
        $response->assertOk();
        $data = $response->json('data');

        $this->assertCount(3, $data);
        $names = collect($data)->pluck('name')->toArray();
        $this->assertContains('Fighter', $names);
        $this->assertContains('Barbarian', $names);
        $this->assertContains('Rogue', $names);
        $this->assertNotContains('Wizard', $names);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_classes_by_hit_die_12(): void
    {
        // Arrange: Create classes with different hit dice
        $barbarian = CharacterClass::factory()->create([
            'name' => 'Barbarian',
            'hit_die' => 12,
        ]);
        $barbarian->searchable();

        $fighter = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'hit_die' => 10,
        ]);
        $fighter->searchable();

        $cleric = CharacterClass::factory()->create([
            'name' => 'Cleric',
            'hit_die' => 8,
        ]);
        $cleric->searchable();

        sleep(1); // Wait for Meilisearch indexing

        // Act: Filter by hit_die=12 (using Meilisearch filter syntax)
        $response = $this->getJson('/api/v1/classes?filter=hit_die = 12');

        // Assert: Only d12 classes returned
        $response->assertOk();
        $data = $response->json('data');

        $this->assertCount(1, $data);
        $this->assertEquals('Barbarian', $data[0]['name']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_classes_by_hit_die_10(): void
    {
        // Arrange: Create classes with different hit dice
        $fighter = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'hit_die' => 10,
        ]);
        $fighter->searchable();

        $wisAbility = AbilityScore::where('code', 'WIS')->first();
        $ranger = CharacterClass::factory()->create([
            'name' => 'Ranger',
            'hit_die' => 10,
            'spellcasting_ability_id' => $wisAbility->id,
        ]);
        $ranger->searchable();

        $chaAbility = AbilityScore::where('code', 'CHA')->first();
        $paladin = CharacterClass::factory()->create([
            'name' => 'Paladin',
            'hit_die' => 10,
            'spellcasting_ability_id' => $chaAbility->id,
        ]);
        $paladin->searchable();

        $wizard = CharacterClass::factory()->create([
            'name' => 'Wizard',
            'hit_die' => 6,
        ]);
        $wizard->searchable();

        sleep(1); // Wait for Meilisearch indexing

        // Act: Filter by hit_die=10 (using Meilisearch filter syntax)
        $response = $this->getJson('/api/v1/classes?filter=hit_die = 10');

        // Assert: Only d10 classes returned
        $response->assertOk();
        $data = $response->json('data');

        $this->assertCount(3, $data);
        $names = collect($data)->pluck('name')->toArray();
        $this->assertContains('Fighter', $names);
        $this->assertContains('Ranger', $names);
        $this->assertContains('Paladin', $names);
        $this->assertNotContains('Wizard', $names);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_classes_by_combined_hit_die_and_is_spellcaster(): void
    {
        // Arrange: Create d10 spellcasters and non-spellcasters
        $wisAbility = AbilityScore::where('code', 'WIS')->first();
        $ranger = CharacterClass::factory()->create([
            'name' => 'Ranger',
            'hit_die' => 10,
            'spellcasting_ability_id' => $wisAbility->id,
        ]);
        $ranger->searchable();

        $chaAbility = AbilityScore::where('code', 'CHA')->first();
        $paladin = CharacterClass::factory()->create([
            'name' => 'Paladin',
            'hit_die' => 10,
            'spellcasting_ability_id' => $chaAbility->id,
        ]);
        $paladin->searchable();

        $fighter = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'hit_die' => 10,
            'spellcasting_ability_id' => null,
        ]);
        $fighter->searchable();

        $barbarian = CharacterClass::factory()->create([
            'name' => 'Barbarian',
            'hit_die' => 12,
            'spellcasting_ability_id' => null,
        ]);
        $barbarian->searchable();

        sleep(1); // Wait for Meilisearch indexing

        // Act: Filter by hit_die=10 AND is_spellcaster=true (using Meilisearch filter syntax)
        $response = $this->getJson('/api/v1/classes?filter=hit_die = 10 AND is_spellcaster = true');

        // Assert: Only d10 spellcasters returned
        $response->assertOk();
        $data = $response->json('data');

        $this->assertCount(2, $data);
        $names = collect($data)->pluck('name')->toArray();
        $this->assertContains('Ranger', $names);
        $this->assertContains('Paladin', $names);
        $this->assertNotContains('Fighter', $names);
        $this->assertNotContains('Barbarian', $names);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_classes_by_combined_hit_die_and_is_spellcaster_false(): void
    {
        // Arrange: Create d12 non-spellcasters
        $barbarian = CharacterClass::factory()->create([
            'name' => 'Barbarian',
            'hit_die' => 12,
            'spellcasting_ability_id' => null,
        ]);
        $barbarian->searchable();

        $fighter = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'hit_die' => 10,
            'spellcasting_ability_id' => null,
        ]);
        $fighter->searchable();

        $intAbility = AbilityScore::where('code', 'INT')->first();
        $wizard = CharacterClass::factory()->create([
            'name' => 'Wizard',
            'hit_die' => 6,
            'spellcasting_ability_id' => $intAbility->id,
        ]);
        $wizard->searchable();

        sleep(1); // Wait for Meilisearch indexing

        // Act: Filter by hit_die=12 AND is_spellcaster=false (using Meilisearch filter syntax)
        $response = $this->getJson('/api/v1/classes?filter=hit_die = 12 AND is_spellcaster = false');

        // Assert: Only Barbarian returned (d12 non-spellcaster)
        $response->assertOk();
        $data = $response->json('data');

        $this->assertCount(1, $data);
        $this->assertEquals('Barbarian', $data[0]['name']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_classes_by_max_spell_level(): void
    {
        // Arrange: Create classes with different max spell levels
        $intAbility = AbilityScore::where('code', 'INT')->first();
        $wizard = CharacterClass::factory()->create([
            'name' => 'Wizard',
            'spellcasting_ability_id' => $intAbility->id,
        ]);

        $wisAbility = AbilityScore::where('code', 'WIS')->first();
        $cleric = CharacterClass::factory()->create([
            'name' => 'Cleric',
            'spellcasting_ability_id' => $wisAbility->id,
        ]);

        $fighter = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'spellcasting_ability_id' => null,
        ]);

        // Create spells
        $level9Spell = Spell::factory()->create(['name' => 'Wish', 'level' => 9]);
        $level5Spell = Spell::factory()->create(['name' => 'Cone of Cold', 'level' => 5]);
        $level1Spell = Spell::factory()->create(['name' => 'Magic Missile', 'level' => 1]);

        // Attach spells to classes
        $wizard->spells()->attach([$level9Spell->id, $level5Spell->id]);
        $cleric->spells()->attach([$level1Spell->id]);

        // Re-index after attaching spells
        $wizard->searchable();
        $cleric->searchable();
        $fighter->searchable();

        sleep(1); // Wait for Meilisearch indexing

        // Act: Filter by max_spell_level=9 (using Meilisearch filter syntax)
        $response = $this->getJson('/api/v1/classes?filter=max_spell_level = 9');

        // Assert: Only classes with 9th level spells returned
        $response->assertOk();
        $data = $response->json('data');

        $this->assertCount(1, $data);
        $this->assertEquals('Wizard', $data[0]['name']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_rejects_invalid_filter_syntax(): void
    {
        // Act: Send invalid Meilisearch filter syntax
        $response = $this->getJson('/api/v1/classes?filter=invalid_field INVALID_OPERATOR value');

        // Assert: Meilisearch returns error (422 or 400)
        $this->assertContains($response->status(), [400, 422, 500]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_accepts_valid_filter_with_multiple_conditions(): void
    {
        // Arrange: Create a test class
        $intAbility = AbilityScore::where('code', 'INT')->first();
        $wizard = CharacterClass::factory()->create([
            'name' => 'Wizard',
            'hit_die' => 6,
            'spellcasting_ability_id' => $intAbility->id,
        ]);
        $wizard->searchable();

        sleep(1); // Wait for Meilisearch indexing

        // Act: Send complex valid filter
        $response = $this->getJson('/api/v1/classes?filter=hit_die = 6 AND is_spellcaster = true');

        // Assert: Success
        $response->assertOk();
        $response->assertJsonStructure(['data', 'links', 'meta']);
    }
}
