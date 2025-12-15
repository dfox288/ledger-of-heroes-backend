<?php

namespace Tests\Feature\Api;

use App\Models\AbilityScore;
use App\Models\Character;
use App\Models\CharacterClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Test that the stats endpoint returns preparation_method for spellcasters.
 *
 * Issue #675: Add preparation_method to character stats endpoint
 *
 * Values:
 * - "known": Bard, Sorcerer, Warlock, Ranger (single-class)
 * - "spellbook": Wizard (single-class)
 * - "prepared": Cleric, Druid, Paladin, Artificer (single-class)
 * - "mixed": Multiclass with different preparation methods
 * - null: Non-spellcaster
 */
class CharacterStatsPreparationMethodTest extends TestCase
{
    use RefreshDatabase;

    private function createSpellcastingClass(string $slug, string $name, string $method): CharacterClass
    {
        $charisma = AbilityScore::firstOrCreate(['code' => 'CHA'], ['name' => 'Charisma']);

        return CharacterClass::factory()->create([
            'slug' => $slug,
            'name' => $name,
            'spell_preparation_method' => $method,
            'spellcasting_ability_id' => $charisma->id,
        ]);
    }

    private function createNonCasterClass(string $slug, string $name): CharacterClass
    {
        return CharacterClass::factory()->create([
            'slug' => $slug,
            'name' => $name,
            'spell_preparation_method' => null,
            'spellcasting_ability_id' => null,
        ]);
    }

    #[Test]
    public function it_returns_known_for_sorcerer(): void
    {
        $sorcerer = $this->createSpellcastingClass('test:sorcerer', 'Sorcerer', 'known');

        $character = Character::factory()
            ->withAbilityScores([])
            ->withClass($sorcerer)
            ->create();

        $response = $this->getJson("/api/v1/characters/{$character->id}/stats");

        $response->assertOk()
            ->assertJsonPath('data.preparation_method', 'known');
    }

    #[Test]
    public function it_returns_spellbook_for_wizard(): void
    {
        $intelligence = AbilityScore::firstOrCreate(['code' => 'INT'], ['name' => 'Intelligence']);
        $wizard = CharacterClass::factory()->create([
            'slug' => 'test:wizard',
            'name' => 'Wizard',
            'spell_preparation_method' => 'spellbook',
            'spellcasting_ability_id' => $intelligence->id,
        ]);

        $character = Character::factory()
            ->withAbilityScores([])
            ->withClass($wizard)
            ->create();

        $response = $this->getJson("/api/v1/characters/{$character->id}/stats");

        $response->assertOk()
            ->assertJsonPath('data.preparation_method', 'spellbook');
    }

    #[Test]
    public function it_returns_prepared_for_cleric(): void
    {
        $wisdom = AbilityScore::firstOrCreate(['code' => 'WIS'], ['name' => 'Wisdom']);
        $cleric = CharacterClass::factory()->create([
            'slug' => 'test:cleric',
            'name' => 'Cleric',
            'spell_preparation_method' => 'prepared',
            'spellcasting_ability_id' => $wisdom->id,
        ]);

        $character = Character::factory()
            ->withAbilityScores([])
            ->withClass($cleric)
            ->create();

        $response = $this->getJson("/api/v1/characters/{$character->id}/stats");

        $response->assertOk()
            ->assertJsonPath('data.preparation_method', 'prepared');
    }

    #[Test]
    public function it_returns_null_for_non_spellcaster(): void
    {
        $fighter = $this->createNonCasterClass('test:fighter', 'Fighter');

        $character = Character::factory()
            ->withAbilityScores([])
            ->withClass($fighter)
            ->create();

        $response = $this->getJson("/api/v1/characters/{$character->id}/stats");

        $response->assertOk()
            ->assertJsonPath('data.preparation_method', null);
    }

    #[Test]
    public function it_returns_mixed_for_multiclass_with_different_methods(): void
    {
        $sorcerer = $this->createSpellcastingClass('test:sorcerer-multi', 'Sorcerer', 'known');

        $wisdom = AbilityScore::firstOrCreate(['code' => 'WIS'], ['name' => 'Wisdom']);
        $cleric = CharacterClass::factory()->create([
            'slug' => 'test:cleric-multi',
            'name' => 'Cleric',
            'spell_preparation_method' => 'prepared',
            'spellcasting_ability_id' => $wisdom->id,
        ]);

        $character = Character::factory()
            ->withAbilityScores([])
            ->withClass($sorcerer, 3)
            ->withClass($cleric, 2)
            ->create();

        $response = $this->getJson("/api/v1/characters/{$character->id}/stats");

        $response->assertOk()
            ->assertJsonPath('data.preparation_method', 'mixed');
    }

    #[Test]
    public function it_returns_same_method_for_multiclass_with_same_method(): void
    {
        $sorcerer = $this->createSpellcastingClass('test:sorcerer-same', 'Sorcerer', 'known');
        $bard = $this->createSpellcastingClass('test:bard-same', 'Bard', 'known');

        $character = Character::factory()
            ->withAbilityScores([])
            ->withClass($sorcerer, 3)
            ->withClass($bard, 2)
            ->create();

        $response = $this->getJson("/api/v1/characters/{$character->id}/stats");

        $response->assertOk()
            ->assertJsonPath('data.preparation_method', 'known');
    }

    #[Test]
    public function it_ignores_non_caster_classes_in_multiclass(): void
    {
        $sorcerer = $this->createSpellcastingClass('test:sorcerer-mix', 'Sorcerer', 'known');
        $fighter = $this->createNonCasterClass('test:fighter-mix', 'Fighter');

        $character = Character::factory()
            ->withAbilityScores([])
            ->withClass($sorcerer, 3)
            ->withClass($fighter, 2)
            ->create();

        $response = $this->getJson("/api/v1/characters/{$character->id}/stats");

        $response->assertOk()
            ->assertJsonPath('data.preparation_method', 'known');
    }

    #[Test]
    public function it_returns_null_for_character_without_class(): void
    {
        $character = Character::factory()
            ->withAbilityScores([])
            ->create();

        $response = $this->getJson("/api/v1/characters/{$character->id}/stats");

        $response->assertOk()
            ->assertJsonPath('data.preparation_method', null);
    }
}
