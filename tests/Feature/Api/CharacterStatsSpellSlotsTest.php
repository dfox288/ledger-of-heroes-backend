<?php

namespace Tests\Feature\Api;

use App\Enums\SpellSlotType;
use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\CharacterSpellSlot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Issue #618: Enrich stats.spell_slots with spent/available
 *
 * The stats endpoint should return spell_slots in the same rich format
 * as the dedicated /spell-slots endpoint, including total, spent, and available.
 */
class CharacterStatsSpellSlotsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    #[Test]
    public function stats_endpoint_returns_enriched_spell_slots_with_tracking(): void
    {
        $wizardClass = CharacterClass::factory()->spellcaster('INT')->create(['name' => 'Wizard']);
        $character = Character::factory()
            ->withClass($wizardClass)
            ->withAbilityScores(['intelligence' => 16])
            ->level(3)
            ->create();

        // Create tracked spell slots with some spent
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

        $response = $this->getJson("/api/v1/characters/{$character->id}/stats");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'spell_slots' => [
                        'slots',
                        'pact_magic',
                    ],
                ],
            ])
            ->assertJsonPath('data.spell_slots.slots.1.total', 4)
            ->assertJsonPath('data.spell_slots.slots.1.spent', 2)
            ->assertJsonPath('data.spell_slots.slots.1.available', 2)
            ->assertJsonPath('data.spell_slots.slots.2.total', 2)
            ->assertJsonPath('data.spell_slots.slots.2.spent', 1)
            ->assertJsonPath('data.spell_slots.slots.2.available', 1)
            ->assertJsonPath('data.spell_slots.pact_magic', null);
    }

    #[Test]
    public function stats_endpoint_returns_pact_magic_for_warlock(): void
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

        $response = $this->getJson("/api/v1/characters/{$character->id}/stats");

        $response->assertOk()
            ->assertJsonPath('data.spell_slots.pact_magic.level', 2)
            ->assertJsonPath('data.spell_slots.pact_magic.total', 2)
            ->assertJsonPath('data.spell_slots.pact_magic.spent', 1)
            ->assertJsonPath('data.spell_slots.pact_magic.available', 1);
    }

    #[Test]
    public function stats_endpoint_initializes_untracked_slots_with_zero_spent(): void
    {
        $wizardClass = CharacterClass::factory()->spellcaster('INT')->create(['name' => 'Wizard']);
        $character = Character::factory()
            ->withClass($wizardClass)
            ->withAbilityScores(['intelligence' => 16])
            ->level(3)
            ->create();

        // No CharacterSpellSlot records exist - should still show slots with spent: 0

        $response = $this->getJson("/api/v1/characters/{$character->id}/stats");

        $response->assertOk()
            ->assertJsonPath('data.spell_slots.slots.1.total', 4)
            ->assertJsonPath('data.spell_slots.slots.1.spent', 0)
            ->assertJsonPath('data.spell_slots.slots.1.available', 4)
            ->assertJsonPath('data.spell_slots.slots.2.total', 2)
            ->assertJsonPath('data.spell_slots.slots.2.spent', 0)
            ->assertJsonPath('data.spell_slots.slots.2.available', 2);
    }

    #[Test]
    public function stats_endpoint_returns_empty_spell_slots_for_non_caster(): void
    {
        $fighterClass = CharacterClass::factory()->create(['name' => 'Fighter']);
        $character = Character::factory()
            ->withClass($fighterClass)
            ->withStandardArray()
            ->level(3)
            ->create();

        $response = $this->getJson("/api/v1/characters/{$character->id}/stats");

        $response->assertOk()
            ->assertJsonPath('data.spell_slots.slots', [])
            ->assertJsonPath('data.spell_slots.pact_magic', null);
    }

    #[Test]
    public function stats_endpoint_includes_preparation_limit_and_count(): void
    {
        $wizardClass = CharacterClass::factory()->spellcaster('INT')->create(['name' => 'Wizard']);
        $character = Character::factory()
            ->withClass($wizardClass)
            ->withAbilityScores(['intelligence' => 16])
            ->level(3)
            ->create();

        // Prepare some spells
        $spells = \App\Models\Spell::factory()->count(2)->create(['level' => 1]);
        foreach ($spells as $spell) {
            \App\Models\CharacterSpell::create([
                'character_id' => $character->id,
                'spell_slug' => $spell->slug,
                'preparation_status' => 'prepared',
                'source' => 'class',
            ]);
        }

        $response = $this->getJson("/api/v1/characters/{$character->id}/stats");

        // preparation_limit and prepared_spell_count stay at top level for backwards compatibility
        $response->assertOk()
            ->assertJsonPath('data.preparation_limit', 6) // Level 3 + INT mod 3
            ->assertJsonPath('data.prepared_spell_count', 2);
    }
}
