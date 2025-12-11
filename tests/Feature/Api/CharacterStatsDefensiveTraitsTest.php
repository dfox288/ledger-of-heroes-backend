<?php

namespace Tests\Feature\Api;

use App\Models\Character;
use App\Models\Condition;
use App\Models\DamageType;
use App\Models\Feat;
use App\Models\Race;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Test that the stats endpoint returns defensive traits (resistances, immunities, vulnerabilities, condition effects).
 *
 * Issue #417: Stats endpoint should include defensive traits from race and feats
 */
class CharacterStatsDefensiveTraitsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_includes_damage_resistances_from_race(): void
    {
        // Get damage type from seeded data
        $poison = DamageType::firstOrCreate(['code' => 'PO'], ['name' => 'Poison']);

        // Create race with poison resistance (like Dwarf)
        $race = Race::factory()->create(['name' => 'Dwarf', 'slug' => 'dwarf']);
        $race->modifiers()->create([
            'modifier_category' => 'damage_resistance',
            'damage_type_id' => $poison->id,
            'value' => '0',
            'condition' => null,
        ]);

        // Create character
        $character = Character::factory()
            ->withAbilityScores([])
            ->create(['race_slug' => $race->slug]);

        // Get stats
        $response = $this->getJson("/api/v1/characters/{$character->id}/stats");

        $response->assertOk()
            ->assertJsonPath('data.damage_resistances.0.type', 'Poison')
            ->assertJsonPath('data.damage_resistances.0.condition', null)
            ->assertJsonPath('data.damage_resistances.0.source', 'Dwarf');
    }

    #[Test]
    public function it_includes_conditional_damage_resistance_text(): void
    {
        // Get damage type from seeded data
        $bludgeoning = DamageType::firstOrCreate(['code' => 'B'], ['name' => 'Bludgeoning']);

        // Create race with conditional resistance (e.g., "from nonmagical attacks")
        $race = Race::factory()->create(['name' => 'Bear Totem Warrior', 'slug' => 'bear-totem']);
        $race->modifiers()->create([
            'modifier_category' => 'damage_resistance',
            'damage_type_id' => $bludgeoning->id,
            'value' => '0',
            'condition' => 'from nonmagical attacks',
        ]);

        // Create character
        $character = Character::factory()
            ->withAbilityScores([])
            ->create(['race_slug' => $race->slug]);

        // Get stats
        $response = $this->getJson("/api/v1/characters/{$character->id}/stats");

        $response->assertOk()
            ->assertJsonPath('data.damage_resistances.0.type', 'Bludgeoning')
            ->assertJsonPath('data.damage_resistances.0.condition', 'from nonmagical attacks')
            ->assertJsonPath('data.damage_resistances.0.source', 'Bear Totem Warrior');
    }

    #[Test]
    public function it_returns_empty_array_when_no_damage_resistances(): void
    {
        // Create race with no resistances
        $race = Race::factory()->create(['name' => 'Human', 'slug' => 'human']);

        // Create character
        $character = Character::factory()
            ->withAbilityScores([])
            ->create(['race_slug' => $race->slug]);

        // Get stats
        $response = $this->getJson("/api/v1/characters/{$character->id}/stats");

        $response->assertOk()
            ->assertJsonPath('data.damage_resistances', []);
    }

    #[Test]
    public function it_includes_damage_immunities_from_race(): void
    {
        // Get damage type from seeded data
        $poison = DamageType::firstOrCreate(['code' => 'PO'], ['name' => 'Poison']);

        // Create race with poison immunity
        $race = Race::factory()->create(['name' => 'Warforged', 'slug' => 'warforged']);
        $race->modifiers()->create([
            'modifier_category' => 'damage_immunity',
            'damage_type_id' => $poison->id,
            'value' => '0',
            'condition' => null,
        ]);

        // Create character
        $character = Character::factory()
            ->withAbilityScores([])
            ->create(['race_slug' => $race->slug]);

        // Get stats
        $response = $this->getJson("/api/v1/characters/{$character->id}/stats");

        $response->assertOk()
            ->assertJsonPath('data.damage_immunities.0.type', 'Poison')
            ->assertJsonPath('data.damage_immunities.0.condition', null)
            ->assertJsonPath('data.damage_immunities.0.source', 'Warforged');
    }

    #[Test]
    public function it_includes_damage_vulnerabilities_from_race(): void
    {
        // Get damage type from seeded data
        $fire = DamageType::firstOrCreate(['code' => 'F'], ['name' => 'Fire']);

        // Create race with fire vulnerability
        $race = Race::factory()->create(['name' => 'Ice Creature', 'slug' => 'ice-creature']);
        $race->modifiers()->create([
            'modifier_category' => 'damage_vulnerability',
            'damage_type_id' => $fire->id,
            'value' => '0',
            'condition' => null,
        ]);

        // Create character
        $character = Character::factory()
            ->withAbilityScores([])
            ->create(['race_slug' => $race->slug]);

        // Get stats
        $response = $this->getJson("/api/v1/characters/{$character->id}/stats");

        $response->assertOk()
            ->assertJsonPath('data.damage_vulnerabilities.0.type', 'Fire')
            ->assertJsonPath('data.damage_vulnerabilities.0.condition', null)
            ->assertJsonPath('data.damage_vulnerabilities.0.source', 'Ice Creature');
    }

    #[Test]
    public function it_includes_condition_advantages_from_race(): void
    {
        // Create condition
        $charmed = Condition::firstOrCreate(['slug' => 'charmed'], ['name' => 'Charmed']);

        // Create race with advantage on saves against charmed (like Elf)
        $race = Race::factory()->create(['name' => 'Elf', 'slug' => 'elf']);
        $race->conditions()->create([
            'condition_id' => $charmed->id,
            'effect_type' => 'advantage',
        ]);

        // Create character
        $character = Character::factory()
            ->withAbilityScores([])
            ->create(['race_slug' => $race->slug]);

        // Get stats
        $response = $this->getJson("/api/v1/characters/{$character->id}/stats");

        $response->assertOk()
            ->assertJsonPath('data.condition_advantages.0.condition', 'Charmed')
            ->assertJsonPath('data.condition_advantages.0.effect', 'advantage')
            ->assertJsonPath('data.condition_advantages.0.source', 'Elf');
    }

    #[Test]
    public function it_returns_empty_array_when_no_condition_advantages(): void
    {
        // Create race with no condition advantages
        $race = Race::factory()->create(['name' => 'Human', 'slug' => 'human']);

        // Create character
        $character = Character::factory()
            ->withAbilityScores([])
            ->create(['race_slug' => $race->slug]);

        // Get stats
        $response = $this->getJson("/api/v1/characters/{$character->id}/stats");

        $response->assertOk()
            ->assertJsonPath('data.condition_advantages', []);
    }

    #[Test]
    public function it_includes_condition_disadvantages_from_race(): void
    {
        // Create condition
        $frightened = Condition::firstOrCreate(['slug' => 'frightened'], ['name' => 'Frightened']);

        // Create race with disadvantage on saves against frightened
        $race = Race::factory()->create(['name' => 'Coward', 'slug' => 'coward']);
        $race->conditions()->create([
            'condition_id' => $frightened->id,
            'effect_type' => 'disadvantage',
        ]);

        // Create character
        $character = Character::factory()
            ->withAbilityScores([])
            ->create(['race_slug' => $race->slug]);

        // Get stats
        $response = $this->getJson("/api/v1/characters/{$character->id}/stats");

        $response->assertOk()
            ->assertJsonPath('data.condition_disadvantages.0.condition', 'Frightened')
            ->assertJsonPath('data.condition_disadvantages.0.effect', 'disadvantage')
            ->assertJsonPath('data.condition_disadvantages.0.source', 'Coward');
    }

    #[Test]
    public function it_includes_condition_immunities_from_race(): void
    {
        // Create condition
        $charmed = Condition::firstOrCreate(['slug' => 'charmed'], ['name' => 'Charmed']);

        // Create race with immunity to charmed
        $race = Race::factory()->create(['name' => 'Elf', 'slug' => 'elf']);
        $race->conditions()->create([
            'condition_id' => $charmed->id,
            'effect_type' => 'immunity',
        ]);

        // Create character
        $character = Character::factory()
            ->withAbilityScores([])
            ->create(['race_slug' => $race->slug]);

        // Get stats
        $response = $this->getJson("/api/v1/characters/{$character->id}/stats");

        $response->assertOk()
            ->assertJsonPath('data.condition_immunities.0.condition', 'Charmed')
            ->assertJsonPath('data.condition_immunities.0.effect', 'immunity')
            ->assertJsonPath('data.condition_immunities.0.source', 'Elf');
    }

    #[Test]
    public function it_includes_defensive_traits_from_feats(): void
    {
        // Get damage type and condition
        $fire = DamageType::firstOrCreate(['code' => 'F'], ['name' => 'Fire']);
        $poisoned = Condition::firstOrCreate(['slug' => 'poisoned'], ['name' => 'Poisoned']);

        // Create feat with fire resistance
        $feat = Feat::factory()->create(['name' => 'Elemental Adept', 'slug' => 'elemental-adept']);
        $feat->modifiers()->create([
            'modifier_category' => 'damage_resistance',
            'damage_type_id' => $fire->id,
            'value' => '0',
            'condition' => null,
        ]);
        $feat->conditions()->create([
            'condition_id' => $poisoned->id,
            'effect_type' => 'advantage',
        ]);

        // Create race and character
        $race = Race::factory()->create(['name' => 'Human', 'slug' => 'human']);
        $character = Character::factory()
            ->withAbilityScores([])
            ->create(['race_slug' => $race->slug]);

        // Add feat to character via features relationship
        $character->features()->create([
            'feature_type' => Feat::class,
            'feature_id' => $feat->id,
            'source' => 'feat',
            'level_acquired' => 1,
        ]);

        // Get stats
        $response = $this->getJson("/api/v1/characters/{$character->id}/stats");

        $response->assertOk()
            ->assertJsonPath('data.damage_resistances.0.type', 'Fire')
            ->assertJsonPath('data.damage_resistances.0.source', 'Elemental Adept')
            ->assertJsonPath('data.condition_advantages.0.condition', 'Poisoned')
            ->assertJsonPath('data.condition_advantages.0.source', 'Elemental Adept');
    }

    #[Test]
    public function it_aggregates_defensive_traits_from_multiple_sources(): void
    {
        // Get damage types from seeded data
        $poison = DamageType::firstOrCreate(['code' => 'PO'], ['name' => 'Poison']);
        $fire = DamageType::firstOrCreate(['code' => 'F'], ['name' => 'Fire']);

        // Create conditions
        $charmed = Condition::firstOrCreate(['slug' => 'charmed'], ['name' => 'Charmed']);
        $frightened = Condition::firstOrCreate(['slug' => 'frightened'], ['name' => 'Frightened']);

        // Create race with poison resistance and charmed advantage
        $race = Race::factory()->create(['name' => 'Dwarf', 'slug' => 'dwarf']);
        $race->modifiers()->create([
            'modifier_category' => 'damage_resistance',
            'damage_type_id' => $poison->id,
            'value' => '0',
            'condition' => null,
        ]);
        $race->conditions()->create([
            'condition_id' => $charmed->id,
            'effect_type' => 'advantage',
        ]);

        // Create feat with fire resistance and frightened advantage
        $feat = Feat::factory()->create(['name' => 'Brave', 'slug' => 'brave']);
        $feat->modifiers()->create([
            'modifier_category' => 'damage_resistance',
            'damage_type_id' => $fire->id,
            'value' => '0',
            'condition' => null,
        ]);
        $feat->conditions()->create([
            'condition_id' => $frightened->id,
            'effect_type' => 'advantage',
        ]);

        // Create character
        $character = Character::factory()
            ->withAbilityScores([])
            ->create(['race_slug' => $race->slug]);

        // Add feat to character
        $character->features()->create([
            'feature_type' => Feat::class,
            'feature_id' => $feat->id,
            'source' => 'feat',
            'level_acquired' => 1,
        ]);

        // Get stats
        $response = $this->getJson("/api/v1/characters/{$character->id}/stats");

        $response->assertOk()
            // Should have 2 resistances
            ->assertJsonCount(2, 'data.damage_resistances')
            ->assertJsonPath('data.damage_resistances.0.type', 'Poison')
            ->assertJsonPath('data.damage_resistances.0.source', 'Dwarf')
            ->assertJsonPath('data.damage_resistances.1.type', 'Fire')
            ->assertJsonPath('data.damage_resistances.1.source', 'Brave')
            // Should have 2 condition advantages
            ->assertJsonCount(2, 'data.condition_advantages')
            ->assertJsonPath('data.condition_advantages.0.condition', 'Charmed')
            ->assertJsonPath('data.condition_advantages.0.source', 'Dwarf')
            ->assertJsonPath('data.condition_advantages.1.condition', 'Frightened')
            ->assertJsonPath('data.condition_advantages.1.source', 'Brave');
    }

    #[Test]
    public function it_handles_character_with_no_race_gracefully(): void
    {
        // Create character with no race
        $character = Character::factory()
            ->withAbilityScores([])
            ->create(['race_slug' => null]);

        // Get stats
        $response = $this->getJson("/api/v1/characters/{$character->id}/stats");

        $response->assertOk()
            ->assertJsonPath('data.damage_resistances', [])
            ->assertJsonPath('data.damage_immunities', [])
            ->assertJsonPath('data.damage_vulnerabilities', [])
            ->assertJsonPath('data.condition_advantages', [])
            ->assertJsonPath('data.condition_disadvantages', [])
            ->assertJsonPath('data.condition_immunities', []);
    }

    #[Test]
    public function it_includes_all_six_defensive_trait_arrays_in_response(): void
    {
        // Create simple character
        $race = Race::factory()->create(['name' => 'Human', 'slug' => 'human']);
        $character = Character::factory()
            ->withAbilityScores([])
            ->create(['race_slug' => $race->slug]);

        // Get stats
        $response = $this->getJson("/api/v1/characters/{$character->id}/stats");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'damage_resistances',
                    'damage_immunities',
                    'damage_vulnerabilities',
                    'condition_advantages',
                    'condition_disadvantages',
                    'condition_immunities',
                ],
            ]);
    }

    #[Test]
    public function it_includes_multiple_damage_resistances_from_single_source(): void
    {
        // Get damage types from seeded data
        $poison = DamageType::firstOrCreate(['code' => 'PO'], ['name' => 'Poison']);
        $necrotic = DamageType::firstOrCreate(['code' => 'N'], ['name' => 'Necrotic']);

        // Create race with multiple resistances (like Yuan-Ti)
        $race = Race::factory()->create(['name' => 'Yuan-Ti Pureblood', 'slug' => 'yuan-ti-pureblood']);
        $race->modifiers()->create([
            'modifier_category' => 'damage_resistance',
            'damage_type_id' => $poison->id,
            'value' => '0',
            'condition' => null,
        ]);
        $race->modifiers()->create([
            'modifier_category' => 'damage_resistance',
            'damage_type_id' => $necrotic->id,
            'value' => '0',
            'condition' => null,
        ]);

        // Create character
        $character = Character::factory()
            ->withAbilityScores([])
            ->create(['race_slug' => $race->slug]);

        // Get stats
        $response = $this->getJson("/api/v1/characters/{$character->id}/stats");

        $response->assertOk()
            ->assertJsonCount(2, 'data.damage_resistances')
            ->assertJsonPath('data.damage_resistances.0.type', 'Poison')
            ->assertJsonPath('data.damage_resistances.0.source', 'Yuan-Ti Pureblood')
            ->assertJsonPath('data.damage_resistances.1.type', 'Necrotic')
            ->assertJsonPath('data.damage_resistances.1.source', 'Yuan-Ti Pureblood');
    }

    #[Test]
    public function it_includes_multiple_condition_effects_from_single_source(): void
    {
        // Create conditions
        $charmed = Condition::firstOrCreate(['slug' => 'charmed'], ['name' => 'Charmed']);
        $frightened = Condition::firstOrCreate(['slug' => 'frightened'], ['name' => 'Frightened']);

        // Create race with multiple condition effects (like Elf variants)
        $race = Race::factory()->create(['name' => 'Elf', 'slug' => 'elf']);
        $race->conditions()->create([
            'condition_id' => $charmed->id,
            'effect_type' => 'advantage',
        ]);
        $race->conditions()->create([
            'condition_id' => $frightened->id,
            'effect_type' => 'advantage',
        ]);

        // Create character
        $character = Character::factory()
            ->withAbilityScores([])
            ->create(['race_slug' => $race->slug]);

        // Get stats
        $response = $this->getJson("/api/v1/characters/{$character->id}/stats");

        $response->assertOk()
            ->assertJsonCount(2, 'data.condition_advantages')
            ->assertJsonPath('data.condition_advantages.0.condition', 'Charmed')
            ->assertJsonPath('data.condition_advantages.0.source', 'Elf')
            ->assertJsonPath('data.condition_advantages.1.condition', 'Frightened')
            ->assertJsonPath('data.condition_advantages.1.source', 'Elf');
    }
}
