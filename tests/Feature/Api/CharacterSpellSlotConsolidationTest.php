<?php

namespace Tests\Feature\Api;

use App\Enums\SpellSlotType;
use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\CharacterSpellSlot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CharacterSpellSlotConsolidationTest extends TestCase
{
    use RefreshDatabase;

    // =========================================================================
    // Consolidated Spell Slots Endpoint Tests
    // =========================================================================

    #[Test]
    public function it_returns_consolidated_spell_slots_with_tracked_data(): void
    {
        $wizardClass = CharacterClass::factory()->spellcaster('INT')->create(['name' => 'Wizard']);
        $character = Character::factory()
            ->withClass($wizardClass)
            ->withAbilityScores(['intelligence' => 16])
            ->level(3)
            ->create();

        // Create tracked spell slots
        CharacterSpellSlot::factory()->create([
            'character_id' => $character->id,
            'spell_level' => 1,
            'max_slots' => 4,
            'used_slots' => 2,
            'slot_type' => SpellSlotType::STANDARD,
        ]);

        CharacterSpellSlot::factory()->create([
            'character_id' => $character->id,
            'spell_level' => 2,
            'max_slots' => 2,
            'used_slots' => 1,
            'slot_type' => SpellSlotType::STANDARD,
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/spell-slots");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'slots',
                    'pact_magic',
                    'preparation_limit',
                    'prepared_count',
                ],
            ])
            ->assertJsonPath('data.slots.1.total', 4)
            ->assertJsonPath('data.slots.1.spent', 2)
            ->assertJsonPath('data.slots.1.available', 2)
            ->assertJsonPath('data.slots.2.total', 2)
            ->assertJsonPath('data.slots.2.spent', 1)
            ->assertJsonPath('data.slots.2.available', 1)
            ->assertJsonPath('data.pact_magic', null);
    }

    #[Test]
    public function it_returns_pact_magic_slots_with_tracked_data(): void
    {
        $warlockClass = CharacterClass::factory()->spellcaster('CHA')->create(['name' => 'Warlock']);
        $character = Character::factory()
            ->withClass($warlockClass)
            ->withAbilityScores(['charisma' => 16])
            ->level(3)
            ->create();

        // Create pact magic spell slots
        CharacterSpellSlot::factory()->create([
            'character_id' => $character->id,
            'spell_level' => 2,
            'max_slots' => 2,
            'used_slots' => 1,
            'slot_type' => SpellSlotType::PACT_MAGIC,
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/spell-slots");

        $response->assertOk()
            ->assertJsonPath('data.pact_magic.level', 2)
            ->assertJsonPath('data.pact_magic.total', 2)
            ->assertJsonPath('data.pact_magic.spent', 1)
            ->assertJsonPath('data.pact_magic.available', 1);
    }

    #[Test]
    public function it_initializes_untracked_slots_with_zero_spent(): void
    {
        $wizardClass = CharacterClass::factory()->spellcaster('INT')->create(['name' => 'Wizard']);
        $character = Character::factory()
            ->withClass($wizardClass)
            ->withAbilityScores(['intelligence' => 16])
            ->level(3)
            ->create();

        // No CharacterSpellSlot records exist

        $response = $this->getJson("/api/v1/characters/{$character->id}/spell-slots");

        $response->assertOk()
            ->assertJsonPath('data.slots.1.total', 4)
            ->assertJsonPath('data.slots.1.spent', 0)
            ->assertJsonPath('data.slots.1.available', 4)
            ->assertJsonPath('data.slots.2.total', 2)
            ->assertJsonPath('data.slots.2.spent', 0)
            ->assertJsonPath('data.slots.2.available', 2);
    }

    #[Test]
    public function it_includes_prepared_count_in_response(): void
    {
        $wizardClass = CharacterClass::factory()->spellcaster('INT')->create(['name' => 'Wizard']);
        $character = Character::factory()
            ->withClass($wizardClass)
            ->withAbilityScores(['intelligence' => 16])
            ->level(3)
            ->create();

        // Prepare some spells
        $spells = \App\Models\Spell::factory()->count(3)->create(['level' => 1]);
        foreach ($spells as $spell) {
            \App\Models\CharacterSpell::create([
                'character_id' => $character->id,
                'spell_id' => $spell->id,
                'preparation_status' => 'prepared',
                'source' => 'class',
            ]);
        }

        $response = $this->getJson("/api/v1/characters/{$character->id}/spell-slots");

        $response->assertOk()
            ->assertJsonPath('data.preparation_limit', 6) // Level 3 + 3 INT mod
            ->assertJsonPath('data.prepared_count', 3);
    }

    // =========================================================================
    // Deprecation Headers Tests
    // =========================================================================

    #[Test]
    public function spell_slots_tracked_endpoint_returns_deprecation_headers(): void
    {
        $character = Character::factory()->create();

        CharacterSpellSlot::factory()->create([
            'character_id' => $character->id,
            'spell_level' => 1,
            'max_slots' => 4,
            'used_slots' => 1,
            'slot_type' => SpellSlotType::STANDARD,
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/spell-slots/tracked");

        $response->assertOk()
            ->assertHeader('Deprecation', 'true')
            ->assertHeader('Sunset', 'Sat, 01 Jun 2026 00:00:00 GMT');

        // Verify Link header contains successor
        $linkHeader = $response->headers->get('Link');
        $this->assertStringContainsString('spell-slots', $linkHeader);
        $this->assertStringContainsString('rel="successor-version"', $linkHeader);
    }
}
