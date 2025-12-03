<?php

namespace Tests\Feature\Api;

use App\Models\Character;
use App\Models\Race;
use App\Models\Size;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests for character attributes: alignment, inspiration, speed/size from race.
 *
 * Covers issues #115 (alignment), #116 (speed/size from race), #119 (inspiration).
 */
class CharacterAttributesTest extends TestCase
{
    use RefreshDatabase;

    // =====================
    // #119 - Inspiration Tests
    // =====================

    #[Test]
    public function it_defaults_has_inspiration_to_false(): void
    {
        $character = Character::factory()->create();

        $response = $this->getJson("/api/v1/characters/{$character->id}");

        $response->assertOk()
            ->assertJsonPath('data.has_inspiration', false);
    }

    #[Test]
    public function it_can_create_character_with_inspiration(): void
    {
        $response = $this->postJson('/api/v1/characters', [
            'name' => 'Inspired Hero',
            'has_inspiration' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.has_inspiration', true);

        $this->assertDatabaseHas('characters', [
            'name' => 'Inspired Hero',
            'has_inspiration' => true,
        ]);
    }

    #[Test]
    public function it_can_update_character_inspiration(): void
    {
        $character = Character::factory()->create(['has_inspiration' => false]);

        $response = $this->patchJson("/api/v1/characters/{$character->id}", [
            'has_inspiration' => true,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.has_inspiration', true);

        $this->assertDatabaseHas('characters', [
            'id' => $character->id,
            'has_inspiration' => true,
        ]);
    }

    #[Test]
    public function it_can_remove_inspiration(): void
    {
        $character = Character::factory()->create(['has_inspiration' => true]);

        $response = $this->patchJson("/api/v1/characters/{$character->id}", [
            'has_inspiration' => false,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.has_inspiration', false);
    }

    #[Test]
    public function it_validates_has_inspiration_is_boolean(): void
    {
        $response = $this->postJson('/api/v1/characters', [
            'name' => 'Test',
            'has_inspiration' => 'not-a-boolean',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['has_inspiration']);
    }

    // =====================
    // #115 - Alignment Tests
    // =====================

    #[Test]
    public function it_can_create_character_with_alignment(): void
    {
        $response = $this->postJson('/api/v1/characters', [
            'name' => 'Paladin Pete',
            'alignment' => 'Lawful Good',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.alignment', 'Lawful Good');

        $this->assertDatabaseHas('characters', [
            'name' => 'Paladin Pete',
            'alignment' => 'Lawful Good',
        ]);
    }

    #[Test]
    public function it_can_update_character_alignment(): void
    {
        $character = Character::factory()->create(['alignment' => 'Lawful Good']);

        $response = $this->patchJson("/api/v1/characters/{$character->id}", [
            'alignment' => 'Chaotic Neutral',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.alignment', 'Chaotic Neutral');
    }

    #[Test]
    public function it_allows_null_alignment(): void
    {
        $character = Character::factory()->create(['alignment' => 'Lawful Good']);

        $response = $this->patchJson("/api/v1/characters/{$character->id}", [
            'alignment' => null,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.alignment', null);
    }

    #[Test]
    public function it_validates_alignment_is_valid_value(): void
    {
        $response = $this->postJson('/api/v1/characters', [
            'name' => 'Test',
            'alignment' => 'Super Evil', // Invalid
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['alignment']);
    }

    #[Test]
    public function it_accepts_all_standard_alignments(): void
    {
        $alignments = [
            'Lawful Good',
            'Neutral Good',
            'Chaotic Good',
            'Lawful Neutral',
            'True Neutral',
            'Chaotic Neutral',
            'Lawful Evil',
            'Neutral Evil',
            'Chaotic Evil',
            'Unaligned',
        ];

        foreach ($alignments as $alignment) {
            $response = $this->postJson('/api/v1/characters', [
                'name' => "Character with {$alignment}",
                'alignment' => $alignment,
            ]);

            $response->assertCreated()
                ->assertJsonPath('data.alignment', $alignment);
        }
    }

    #[Test]
    public function it_returns_alignment_in_show_response(): void
    {
        $character = Character::factory()->create(['alignment' => 'Chaotic Good']);

        $response = $this->getJson("/api/v1/characters/{$character->id}");

        $response->assertOk()
            ->assertJsonPath('data.alignment', 'Chaotic Good');
    }

    // =====================
    // #116 - Speed and Size from Race Tests
    // =====================

    #[Test]
    public function it_derives_speed_from_race(): void
    {
        $size = Size::firstOrCreate(['code' => 'M'], ['name' => 'Medium']);
        $race = Race::factory()->create([
            'name' => 'Human',
            'speed' => 30,
            'size_id' => $size->id,
        ]);
        $character = Character::factory()->withRace($race)->create();

        $response = $this->getJson("/api/v1/characters/{$character->id}");

        $response->assertOk()
            ->assertJsonPath('data.speed', 30);
    }

    #[Test]
    public function it_derives_size_from_race(): void
    {
        $size = Size::firstOrCreate(['code' => 'S'], ['name' => 'Small']);
        $race = Race::factory()->create([
            'name' => 'Halfling',
            'speed' => 25,
            'size_id' => $size->id,
        ]);
        $character = Character::factory()->withRace($race)->create();

        $response = $this->getJson("/api/v1/characters/{$character->id}");

        $response->assertOk()
            ->assertJsonPath('data.size', 'Small');
    }

    #[Test]
    public function it_returns_null_speed_when_no_race(): void
    {
        $character = Character::factory()->create(['race_id' => null]);

        $response = $this->getJson("/api/v1/characters/{$character->id}");

        $response->assertOk()
            ->assertJsonPath('data.speed', null);
    }

    #[Test]
    public function it_returns_null_size_when_no_race(): void
    {
        $character = Character::factory()->create(['race_id' => null]);

        $response = $this->getJson("/api/v1/characters/{$character->id}");

        $response->assertOk()
            ->assertJsonPath('data.size', null);
    }

    #[Test]
    public function it_includes_all_movement_speeds_from_race(): void
    {
        $size = Size::firstOrCreate(['code' => 'M'], ['name' => 'Medium']);
        $race = Race::factory()->create([
            'name' => 'Aarakocra',
            'speed' => 25,
            'fly_speed' => 50,
            'swim_speed' => null,
            'climb_speed' => null,
            'size_id' => $size->id,
        ]);
        $character = Character::factory()->withRace($race)->create();

        $response = $this->getJson("/api/v1/characters/{$character->id}");

        $response->assertOk()
            ->assertJsonPath('data.speed', 25)
            ->assertJsonPath('data.speeds.walk', 25)
            ->assertJsonPath('data.speeds.fly', 50)
            ->assertJsonPath('data.speeds.swim', null)
            ->assertJsonPath('data.speeds.climb', null);
    }

    #[Test]
    public function it_updates_speed_and_size_when_race_changes(): void
    {
        $smallSize = Size::firstOrCreate(['code' => 'S'], ['name' => 'Small']);
        $mediumSize = Size::firstOrCreate(['code' => 'M'], ['name' => 'Medium']);

        $halfling = Race::factory()->create([
            'name' => 'Halfling',
            'speed' => 25,
            'size_id' => $smallSize->id,
        ]);
        $human = Race::factory()->create([
            'name' => 'Human',
            'speed' => 30,
            'size_id' => $mediumSize->id,
        ]);

        $character = Character::factory()->withRace($halfling)->create();

        // Verify initial state
        $response = $this->getJson("/api/v1/characters/{$character->id}");
        $response->assertOk()
            ->assertJsonPath('data.speed', 25)
            ->assertJsonPath('data.size', 'Small');

        // Change race
        $response = $this->patchJson("/api/v1/characters/{$character->id}", [
            'race_id' => $human->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.speed', 30)
            ->assertJsonPath('data.size', 'Medium');
    }
}
