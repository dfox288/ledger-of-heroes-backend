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

        $cleric = CharacterClass::factory()->create([
            'name' => 'Cleric',
            'spellcasting_ability_id' => $clericAbility->id,
            'hit_die' => 8,
        ]);

        // Create non-spellcasters (no spellcasting ability)
        $fighter = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'spellcasting_ability_id' => null,
            'hit_die' => 10,
        ]);

        $barbarian = CharacterClass::factory()->create([
            'name' => 'Barbarian',
            'spellcasting_ability_id' => null,
            'hit_die' => 12,
        ]);

        // Act: Filter by is_spellcaster=true
        $response = $this->getJson('/api/v1/classes?is_spellcaster=true');

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

        // Create non-spellcasters
        $fighter = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'spellcasting_ability_id' => null,
            'hit_die' => 10,
        ]);

        $barbarian = CharacterClass::factory()->create([
            'name' => 'Barbarian',
            'spellcasting_ability_id' => null,
            'hit_die' => 12,
        ]);

        $rogue = CharacterClass::factory()->create([
            'name' => 'Rogue',
            'spellcasting_ability_id' => null,
            'hit_die' => 8,
        ]);

        // Act: Filter by is_spellcaster=false
        $response = $this->getJson('/api/v1/classes?is_spellcaster=false');

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

        $fighter = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'hit_die' => 10,
        ]);

        $cleric = CharacterClass::factory()->create([
            'name' => 'Cleric',
            'hit_die' => 8,
        ]);

        // Act: Filter by hit_die=12
        $response = $this->getJson('/api/v1/classes?hit_die=12');

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

        $wisAbility = AbilityScore::where('code', 'WIS')->first();
        $ranger = CharacterClass::factory()->create([
            'name' => 'Ranger',
            'hit_die' => 10,
            'spellcasting_ability_id' => $wisAbility->id,
        ]);

        $chaAbility = AbilityScore::where('code', 'CHA')->first();
        $paladin = CharacterClass::factory()->create([
            'name' => 'Paladin',
            'hit_die' => 10,
            'spellcasting_ability_id' => $chaAbility->id,
        ]);

        $wizard = CharacterClass::factory()->create([
            'name' => 'Wizard',
            'hit_die' => 6,
        ]);

        // Act: Filter by hit_die=10
        $response = $this->getJson('/api/v1/classes?hit_die=10');

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

        $chaAbility = AbilityScore::where('code', 'CHA')->first();
        $paladin = CharacterClass::factory()->create([
            'name' => 'Paladin',
            'hit_die' => 10,
            'spellcasting_ability_id' => $chaAbility->id,
        ]);

        $fighter = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'hit_die' => 10,
            'spellcasting_ability_id' => null,
        ]);

        $barbarian = CharacterClass::factory()->create([
            'name' => 'Barbarian',
            'hit_die' => 12,
            'spellcasting_ability_id' => null,
        ]);

        // Act: Filter by hit_die=10 AND is_spellcaster=true
        $response = $this->getJson('/api/v1/classes?hit_die=10&is_spellcaster=true');

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

        $fighter = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'hit_die' => 10,
            'spellcasting_ability_id' => null,
        ]);

        $intAbility = AbilityScore::where('code', 'INT')->first();
        $wizard = CharacterClass::factory()->create([
            'name' => 'Wizard',
            'hit_die' => 6,
            'spellcasting_ability_id' => $intAbility->id,
        ]);

        // Act: Filter by hit_die=12 AND is_spellcaster=false
        $response = $this->getJson('/api/v1/classes?hit_die=12&is_spellcaster=false');

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

        // Act: Filter by max_spell_level=9
        $response = $this->getJson('/api/v1/classes?max_spell_level=9');

        // Assert: Only classes with 9th level spells returned
        $response->assertOk();
        $data = $response->json('data');

        $this->assertCount(1, $data);
        $this->assertEquals('Wizard', $data[0]['name']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_validates_is_spellcaster_parameter(): void
    {
        // Act: Send invalid boolean value
        $response = $this->getJson('/api/v1/classes?is_spellcaster=invalid');

        // Assert: Validation error
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('is_spellcaster');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_validates_hit_die_parameter(): void
    {
        // Act: Send invalid hit_die value
        $response = $this->getJson('/api/v1/classes?hit_die=15');

        // Assert: Validation error (only 6, 8, 10, 12 allowed)
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('hit_die');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_validates_max_spell_level_parameter(): void
    {
        // Act: Send invalid max_spell_level value
        $response = $this->getJson('/api/v1/classes?max_spell_level=10');

        // Assert: Validation error (only 0-9 allowed)
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('max_spell_level');
    }
}
