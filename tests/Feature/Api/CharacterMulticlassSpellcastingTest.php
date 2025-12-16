<?php

namespace Tests\Feature\Api;

use App\Models\AbilityScore;
use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\CharacterClassPivot;
use App\Models\CharacterSpell;
use App\Models\ClassLevelProgression;
use App\Models\Spell;
use Database\Seeders\MulticlassSpellSlotSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests for Issue #692: Multiclass spellcasting API support.
 *
 * Per PHB p.164-165, multiclass spellcasters have:
 * - Per-class spell lists with class_slug identifying source class
 * - Per-class spellcasting stats (DC, attack bonus, preparation limit)
 * - Separate Warlock pact magic (already implemented)
 */
class CharacterMulticlassSpellcastingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MulticlassSpellSlotSeeder::class);
        $this->seedAbilityScores();
    }

    // =====================
    // CharacterSpellResource Tests
    // =====================

    #[Test]
    public function it_includes_class_slug_in_spell_response(): void
    {
        $character = Character::factory()->create();
        $wizard = $this->createSpellcaster('Wizard', 'INT');
        $spell = Spell::factory()->create(['name' => 'Magic Missile', 'level' => 1]);

        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $wizard->slug,
            'level' => 3,
            'is_primary' => true,
            'order' => 1,
        ]);

        CharacterSpell::create([
            'character_id' => $character->id,
            'spell_slug' => $spell->slug,
            'preparation_status' => 'known',
            'source' => 'class',
            'class_slug' => $wizard->slug,
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/spells");

        $response->assertOk()
            ->assertJsonPath('data.0.class_slug', $wizard->slug)
            ->assertJsonPath('data.0.source', 'class');
    }

    #[Test]
    public function it_shows_different_class_slugs_for_multiclass_spells(): void
    {
        $character = Character::factory()->create();
        $wizard = $this->createSpellcaster('Wizard', 'INT');
        $cleric = $this->createSpellcaster('Cleric', 'WIS');
        $wizardSpell = Spell::factory()->create(['name' => 'Shield', 'level' => 1]);
        $clericSpell = Spell::factory()->create(['name' => 'Cure Wounds', 'level' => 1]);

        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $wizard->slug,
            'level' => 3,
            'is_primary' => true,
            'order' => 1,
        ]);
        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $cleric->slug,
            'level' => 3,
            'is_primary' => false,
            'order' => 2,
        ]);

        CharacterSpell::create([
            'character_id' => $character->id,
            'spell_slug' => $wizardSpell->slug,
            'preparation_status' => 'prepared',
            'source' => 'class',
            'class_slug' => $wizard->slug,
        ]);
        CharacterSpell::create([
            'character_id' => $character->id,
            'spell_slug' => $clericSpell->slug,
            'preparation_status' => 'prepared',
            'source' => 'class',
            'class_slug' => $cleric->slug,
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/spells");

        $response->assertOk()
            ->assertJsonCount(2, 'data');

        $spells = collect($response->json('data'));
        $shieldSpell = $spells->firstWhere('spell.name', 'Shield');
        $cureSpell = $spells->firstWhere('spell.name', 'Cure Wounds');

        $this->assertEquals($wizard->slug, $shieldSpell['class_slug']);
        $this->assertEquals($cleric->slug, $cureSpell['class_slug']);
    }

    // =====================
    // Per-Class Spellcasting Stats Tests
    // =====================

    #[Test]
    public function it_returns_per_class_spellcasting_stats_for_multiclass(): void
    {
        $character = Character::factory()->create([
            'strength' => 10,
            'dexterity' => 10,
            'constitution' => 10,
            'intelligence' => 16, // +3 mod
            'wisdom' => 14, // +2 mod
            'charisma' => 10,
        ]);
        $wizard = $this->createSpellcaster('Wizard', 'INT');
        $cleric = $this->createSpellcaster('Cleric', 'WIS');

        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $wizard->slug,
            'level' => 3,
            'is_primary' => true,
            'order' => 1,
        ]);
        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $cleric->slug,
            'level' => 3,
            'is_primary' => false,
            'order' => 2,
        ]);

        // Use the /stats endpoint which returns CharacterStatsResource
        $response = $this->getJson("/api/v1/characters/{$character->id}/stats");

        $response->assertOk();

        // Spellcasting should be keyed by class slug
        $spellcasting = $response->json('data.spellcasting');

        // Wizard: INT 16 (+3), prof bonus 3 at level 6 total
        // DC = 8 + prof + mod = 8 + 3 + 3 = 14
        // Attack = prof + mod = 3 + 3 = 6
        $this->assertArrayHasKey($wizard->slug, $spellcasting);
        $this->assertEquals('INT', $spellcasting[$wizard->slug]['ability']);
        $this->assertEquals(14, $spellcasting[$wizard->slug]['spell_save_dc']);
        $this->assertEquals(6, $spellcasting[$wizard->slug]['spell_attack_bonus']);

        // Cleric: WIS 14 (+2), prof bonus 3
        // DC = 8 + 3 + 2 = 13
        // Attack = 3 + 2 = 5
        $this->assertArrayHasKey($cleric->slug, $spellcasting);
        $this->assertEquals('WIS', $spellcasting[$cleric->slug]['ability']);
        $this->assertEquals(13, $spellcasting[$cleric->slug]['spell_save_dc']);
        $this->assertEquals(5, $spellcasting[$cleric->slug]['spell_attack_bonus']);
    }

    #[Test]
    public function it_returns_single_class_spellcasting_for_non_multiclass(): void
    {
        $character = Character::factory()->create([
            'intelligence' => 16,
        ]);
        $wizard = $this->createSpellcaster('Wizard', 'INT');

        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $wizard->slug,
            'level' => 5,
            'is_primary' => true,
            'order' => 1,
        ]);

        // Use the /stats endpoint which returns CharacterStatsResource
        $response = $this->getJson("/api/v1/characters/{$character->id}/stats");

        $response->assertOk();

        $spellcasting = $response->json('data.spellcasting');

        // Single caster still uses per-class format for consistency
        $this->assertArrayHasKey($wizard->slug, $spellcasting);
        $this->assertEquals('INT', $spellcasting[$wizard->slug]['ability']);
    }

    #[Test]
    public function it_excludes_non_spellcasting_classes_from_spellcasting_stats(): void
    {
        $character = Character::factory()->create([
            'intelligence' => 16,
        ]);
        $wizard = $this->createSpellcaster('Wizard', 'INT');
        $fighter = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'slug' => 'fighter',
            'spellcasting_ability_id' => null, // No spellcasting
        ]);

        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $fighter->slug,
            'level' => 5,
            'is_primary' => true,
            'order' => 1,
        ]);
        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $wizard->slug,
            'level' => 3,
            'is_primary' => false,
            'order' => 2,
        ]);

        // Use the /stats endpoint which returns CharacterStatsResource
        $response = $this->getJson("/api/v1/characters/{$character->id}/stats");

        $response->assertOk();

        $spellcasting = $response->json('data.spellcasting');

        // Only Wizard should appear (Fighter has no spellcasting)
        $this->assertArrayHasKey($wizard->slug, $spellcasting);
        $this->assertArrayNotHasKey('fighter', $spellcasting);
    }

    // =====================
    // Helpers
    // =====================

    private function seedAbilityScores(): void
    {
        $abilities = [
            ['id' => 1, 'code' => 'STR', 'name' => 'Strength'],
            ['id' => 2, 'code' => 'DEX', 'name' => 'Dexterity'],
            ['id' => 3, 'code' => 'CON', 'name' => 'Constitution'],
            ['id' => 4, 'code' => 'INT', 'name' => 'Intelligence'],
            ['id' => 5, 'code' => 'WIS', 'name' => 'Wisdom'],
            ['id' => 6, 'code' => 'CHA', 'name' => 'Charisma'],
        ];

        foreach ($abilities as $ability) {
            AbilityScore::updateOrCreate(['id' => $ability['id']], $ability);
        }
    }

    private function createSpellcaster(string $name, string $abilityCode): CharacterClass
    {
        $abilityId = match ($abilityCode) {
            'INT' => 4,
            'WIS' => 5,
            'CHA' => 6,
            default => 4,
        };

        $class = CharacterClass::factory()->create([
            'name' => $name,
            'slug' => strtolower($name),
            'parent_class_id' => null,
            'spellcasting_ability_id' => $abilityId,
        ]);

        // Create level progression (full caster)
        ClassLevelProgression::create([
            'class_id' => $class->id,
            'level' => 20,
            'proficiency_bonus' => 6,
            'spell_slots_1st' => 4,
            'spell_slots_2nd' => 3,
            'spell_slots_3rd' => 3,
            'spell_slots_4th' => 3,
            'spell_slots_5th' => 3,
            'spell_slots_6th' => 2,
            'spell_slots_7th' => 2,
            'spell_slots_8th' => 1,
            'spell_slots_9th' => 1,
        ]);

        return $class;
    }
}
