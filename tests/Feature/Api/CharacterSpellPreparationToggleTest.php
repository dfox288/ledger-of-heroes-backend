<?php

namespace Tests\Feature\Api;

use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\CharacterSpell;
use App\Models\Spell;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CharacterSpellPreparationToggleTest extends TestCase
{
    use RefreshDatabase;

    // =====================
    // Toggle Preparation Tests
    // =====================

    #[Test]
    public function it_prepares_a_known_spell_via_toggle(): void
    {
        $character = Character::factory()->create();
        $spell = Spell::factory()->create(['level' => 1]);

        $characterSpell = CharacterSpell::create([
            'character_id' => $character->id,
            'spell_slug' => $spell->slug,
            'preparation_status' => 'known',
            'source' => 'class',
        ]);

        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/spells/{$characterSpell->id}",
            ['is_prepared' => true]
        );

        $response->assertOk()
            ->assertJsonPath('data.preparation_status', 'prepared')
            ->assertJsonPath('data.is_prepared', true);

        $this->assertDatabaseHas('character_spells', [
            'id' => $characterSpell->id,
            'preparation_status' => 'prepared',
        ]);
    }

    #[Test]
    public function it_unprepares_a_prepared_spell_via_toggle(): void
    {
        $character = Character::factory()->create();
        $spell = Spell::factory()->create(['level' => 1]);

        $characterSpell = CharacterSpell::create([
            'character_id' => $character->id,
            'spell_slug' => $spell->slug,
            'preparation_status' => 'prepared',
            'source' => 'class',
        ]);

        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/spells/{$characterSpell->id}",
            ['is_prepared' => false]
        );

        $response->assertOk()
            ->assertJsonPath('data.preparation_status', 'known')
            ->assertJsonPath('data.is_prepared', false);

        $this->assertDatabaseHas('character_spells', [
            'id' => $characterSpell->id,
            'preparation_status' => 'known',
        ]);
    }

    #[Test]
    public function it_cannot_unprepare_always_prepared_spell_via_toggle(): void
    {
        $character = Character::factory()->create();
        $spell = Spell::factory()->create(['level' => 1]);

        $characterSpell = CharacterSpell::create([
            'character_id' => $character->id,
            'spell_slug' => $spell->slug,
            'preparation_status' => 'always_prepared',
            'source' => 'class',
        ]);

        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/spells/{$characterSpell->id}",
            ['is_prepared' => false]
        );

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'Cannot unprepare an always-prepared spell.');

        $this->assertDatabaseHas('character_spells', [
            'id' => $characterSpell->id,
            'preparation_status' => 'always_prepared',
        ]);
    }

    #[Test]
    public function it_cannot_prepare_cantrip_via_toggle(): void
    {
        $character = Character::factory()->create();
        $cantrip = Spell::factory()->cantrip()->create();

        $characterSpell = CharacterSpell::create([
            'character_id' => $character->id,
            'spell_slug' => $cantrip->slug,
            'preparation_status' => 'known',
            'source' => 'class',
        ]);

        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/spells/{$characterSpell->id}",
            ['is_prepared' => true]
        );

        $response->assertUnprocessable();
    }

    #[Test]
    public function it_cannot_exceed_preparation_limit_via_toggle(): void
    {
        $wizardClass = CharacterClass::factory()->spellcaster('INT')->create(['name' => 'Wizard']);
        $character = Character::factory()
            ->withClass($wizardClass)
            ->withAbilityScores(['intelligence' => 12]) // +1 modifier
            ->level(1)
            ->create();

        $spells = Spell::factory()->count(5)->create(['level' => 1]);
        $wizardClass->spells()->attach($spells->pluck('id'));

        // Preparation limit for level 1 wizard with +1 INT = 2 spells
        // Prepare the maximum number of spells
        foreach ($spells->take(2) as $spell) {
            CharacterSpell::create([
                'character_id' => $character->id,
                'spell_slug' => $spell->slug,
                'preparation_status' => 'prepared',
                'source' => 'class',
            ]);
        }

        // Create a third spell that is known but not prepared
        $thirdSpell = CharacterSpell::create([
            'character_id' => $character->id,
            'spell_slug' => $spells[2]->slug,
            'preparation_status' => 'known',
            'source' => 'class',
        ]);

        // Try to prepare beyond the limit
        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/spells/{$thirdSpell->id}",
            ['is_prepared' => true]
        );

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'Preparation limit reached. Unprepare a spell first.');
    }

    #[Test]
    public function it_requires_is_prepared_field(): void
    {
        $character = Character::factory()->create();
        $spell = Spell::factory()->create(['level' => 1]);

        $characterSpell = CharacterSpell::create([
            'character_id' => $character->id,
            'spell_slug' => $spell->slug,
            'preparation_status' => 'known',
            'source' => 'class',
        ]);

        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/spells/{$characterSpell->id}",
            []
        );

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['is_prepared']);
    }

    #[Test]
    public function it_validates_is_prepared_is_boolean(): void
    {
        $character = Character::factory()->create();
        $spell = Spell::factory()->create(['level' => 1]);

        $characterSpell = CharacterSpell::create([
            'character_id' => $character->id,
            'spell_slug' => $spell->slug,
            'preparation_status' => 'known',
            'source' => 'class',
        ]);

        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/spells/{$characterSpell->id}",
            ['is_prepared' => 'not-a-boolean']
        );

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['is_prepared']);
    }

    #[Test]
    public function it_returns_404_for_nonexistent_character_spell(): void
    {
        $character = Character::factory()->create();

        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/spells/99999",
            ['is_prepared' => true]
        );

        $response->assertNotFound();
    }

    #[Test]
    public function it_returns_404_for_spell_belonging_to_different_character(): void
    {
        $character1 = Character::factory()->create();
        $character2 = Character::factory()->create();
        $spell = Spell::factory()->create(['level' => 1]);

        $characterSpell = CharacterSpell::create([
            'character_id' => $character1->id,
            'spell_slug' => $spell->slug,
            'preparation_status' => 'known',
            'source' => 'class',
        ]);

        // Try to update character1's spell via character2's endpoint
        $response = $this->patchJson(
            "/api/v1/characters/{$character2->id}/spells/{$characterSpell->id}",
            ['is_prepared' => true]
        );

        $response->assertNotFound();
    }

    #[Test]
    public function it_is_idempotent_when_preparing_already_prepared_spell(): void
    {
        $character = Character::factory()->create();
        $spell = Spell::factory()->create(['level' => 1]);

        $characterSpell = CharacterSpell::create([
            'character_id' => $character->id,
            'spell_slug' => $spell->slug,
            'preparation_status' => 'prepared',
            'source' => 'class',
        ]);

        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/spells/{$characterSpell->id}",
            ['is_prepared' => true]
        );

        $response->assertOk()
            ->assertJsonPath('data.preparation_status', 'prepared');
    }

    #[Test]
    public function it_is_idempotent_when_unpreparing_already_known_spell(): void
    {
        $character = Character::factory()->create();
        $spell = Spell::factory()->create(['level' => 1]);

        $characterSpell = CharacterSpell::create([
            'character_id' => $character->id,
            'spell_slug' => $spell->slug,
            'preparation_status' => 'known',
            'source' => 'class',
        ]);

        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/spells/{$characterSpell->id}",
            ['is_prepared' => false]
        );

        $response->assertOk()
            ->assertJsonPath('data.preparation_status', 'known');
    }

    #[Test]
    public function it_returns_full_character_spell_resource(): void
    {
        $character = Character::factory()->create();
        $spell = Spell::factory()->create(['level' => 1]);

        $characterSpell = CharacterSpell::create([
            'character_id' => $character->id,
            'spell_slug' => $spell->slug,
            'preparation_status' => 'known',
            'source' => 'class',
        ]);

        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/spells/{$characterSpell->id}",
            ['is_prepared' => true]
        );

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'spell',
                    'spell_slug',
                    'is_dangling',
                    'preparation_status',
                    'source',
                    'is_prepared',
                    'is_always_prepared',
                ],
            ]);
    }
}
