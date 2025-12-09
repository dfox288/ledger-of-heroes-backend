<?php

use App\Models\AbilityScore;
use App\Models\Character;
use App\Models\CharacterAbilityScore;
use App\Models\CharacterFeature;
use App\Models\Feat;
use App\Models\Modifier;
use App\Models\Race;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    // Seed ability scores lookup table for all tests
    AbilityScore::firstOrCreate(['code' => 'STR'], ['name' => 'Strength']);
    AbilityScore::firstOrCreate(['code' => 'DEX'], ['name' => 'Dexterity']);
    AbilityScore::firstOrCreate(['code' => 'CON'], ['name' => 'Constitution']);
    AbilityScore::firstOrCreate(['code' => 'INT'], ['name' => 'Intelligence']);
    AbilityScore::firstOrCreate(['code' => 'WIS'], ['name' => 'Wisdom']);
    AbilityScore::firstOrCreate(['code' => 'CHA'], ['name' => 'Charisma']);
});

describe('Character Ability Bonuses Endpoint', function () {
    it('returns empty bonuses for character with no race', function () {
        $character = Character::factory()->create();

        $response = $this->getJson("/api/v1/characters/{$character->public_id}/ability-bonuses");

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'bonuses' => [],
                    'totals' => [
                        'STR' => 0,
                        'DEX' => 0,
                        'CON' => 0,
                        'INT' => 0,
                        'WIS' => 0,
                        'CHA' => 0,
                    ],
                ],
            ]);
    });

    it('returns fixed racial bonuses with correct structure', function () {
        $str = AbilityScore::where('code', 'STR')->first();
        $con = AbilityScore::where('code', 'CON')->first();

        $race = Race::factory()->create(['name' => 'Mountain Dwarf', 'full_slug' => 'phb:mountain-dwarf']);

        // Create fixed racial modifiers
        Modifier::create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'modifier_category' => 'ability_score',
            'ability_score_id' => $str->id,
            'value' => '2',
            'is_choice' => false,
        ]);

        Modifier::create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'modifier_category' => 'ability_score',
            'ability_score_id' => $con->id,
            'value' => '2',
            'is_choice' => false,
        ]);

        $character = Character::factory()->withRace($race)->create();

        $response = $this->getJson("/api/v1/characters/{$character->public_id}/ability-bonuses");

        $response->assertOk()
            ->assertJsonCount(2, 'data.bonuses')
            ->assertJsonPath('data.totals.STR', 2)
            ->assertJsonPath('data.totals.CON', 2)
            ->assertJsonPath('data.totals.DEX', 0);

        // Check bonuses using collection to avoid order dependency
        $bonuses = collect($response->json('data.bonuses'));

        $strBonus = $bonuses->firstWhere('ability_code', 'STR');
        expect($strBonus['source_type'])->toBe('race');
        expect($strBonus['source_name'])->toBe('Mountain Dwarf');
        expect($strBonus['source_slug'])->toBe('phb:mountain-dwarf');
        expect($strBonus['ability_name'])->toBe('Strength');
        expect($strBonus['value'])->toBe(2);
        expect($strBonus['is_choice'])->toBe(false);
        expect($strBonus)->not->toHaveKey('choice_resolved');
        expect($strBonus)->not->toHaveKey('modifier_id');

        $conBonus = $bonuses->firstWhere('ability_code', 'CON');
        expect($conBonus['source_type'])->toBe('race');
        expect($conBonus['source_name'])->toBe('Mountain Dwarf');
        expect($conBonus['source_slug'])->toBe('phb:mountain-dwarf');
        expect($conBonus['ability_name'])->toBe('Constitution');
        expect($conBonus['value'])->toBe(2);
        expect($conBonus['is_choice'])->toBe(false);
        expect($conBonus)->not->toHaveKey('choice_resolved');
        expect($conBonus)->not->toHaveKey('modifier_id');
    });

    it('returns resolved racial choice bonuses with metadata', function () {
        $str = AbilityScore::where('code', 'STR')->first();
        $dex = AbilityScore::where('code', 'DEX')->first();
        $cha = AbilityScore::where('code', 'CHA')->first();

        $race = Race::factory()->create(['name' => 'Half-Elf', 'full_slug' => 'phb:half-elf']);

        // Fixed CHA bonus
        Modifier::create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'modifier_category' => 'ability_score',
            'ability_score_id' => $cha->id,
            'value' => '2',
            'is_choice' => false,
        ]);

        // Choice modifier (pick 2 abilities for +1 each)
        $choiceModifier = Modifier::create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'modifier_category' => 'ability_score',
            'ability_score_id' => null,
            'value' => '1',
            'is_choice' => true,
            'choice_count' => 2,
        ]);

        $character = Character::factory()->withRace($race)->create();

        // Player chooses STR and DEX
        CharacterAbilityScore::create([
            'character_id' => $character->id,
            'ability_score_code' => 'STR',
            'bonus' => 1,
            'source' => 'race',
            'modifier_id' => $choiceModifier->id,
        ]);

        CharacterAbilityScore::create([
            'character_id' => $character->id,
            'ability_score_code' => 'DEX',
            'bonus' => 1,
            'source' => 'race',
            'modifier_id' => $choiceModifier->id,
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->public_id}/ability-bonuses");

        $response->assertOk()
            ->assertJsonCount(3, 'data.bonuses');

        // Check fixed CHA bonus
        $chaBonus = collect($response->json('data.bonuses'))->firstWhere('ability_code', 'CHA');
        expect($chaBonus['value'])->toBe(2);
        expect($chaBonus['is_choice'])->toBe(false);
        expect($chaBonus)->not->toHaveKey('choice_resolved');

        // Check resolved choice bonuses
        $strBonus = collect($response->json('data.bonuses'))->firstWhere('ability_code', 'STR');
        expect($strBonus['value'])->toBe(1);
        expect($strBonus['is_choice'])->toBe(true);
        expect($strBonus['choice_resolved'])->toBe(true);
        expect($strBonus['modifier_id'])->toBe($choiceModifier->id);

        $dexBonus = collect($response->json('data.bonuses'))->firstWhere('ability_code', 'DEX');
        expect($dexBonus['value'])->toBe(1);
        expect($dexBonus['is_choice'])->toBe(true);
        expect($dexBonus['choice_resolved'])->toBe(true);

        // Check totals
        $response->assertJsonPath('data.totals.CHA', 2)
            ->assertJsonPath('data.totals.STR', 1)
            ->assertJsonPath('data.totals.DEX', 1)
            ->assertJsonPath('data.totals.CON', 0);
    });

    it('returns feat bonuses with correct structure', function () {
        $cha = AbilityScore::where('code', 'CHA')->first();

        $feat = Feat::factory()->create(['name' => 'Actor', 'full_slug' => 'phb:actor']);

        // Actor feat grants +1 CHA
        Modifier::create([
            'reference_type' => Feat::class,
            'reference_id' => $feat->id,
            'modifier_category' => 'ability_score',
            'ability_score_id' => $cha->id,
            'value' => '1',
            'is_choice' => false,
        ]);

        $character = Character::factory()->create();

        // Add feat to character
        CharacterFeature::create([
            'character_id' => $character->id,
            'feature_type' => Feat::class,
            'feature_id' => $feat->id,
            'feature_slug' => $feat->full_slug,
            'source' => 'feat',
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->public_id}/ability-bonuses");

        $response->assertOk()
            ->assertJsonCount(1, 'data.bonuses')
            ->assertJsonPath('data.bonuses.0.source_type', 'feat')
            ->assertJsonPath('data.bonuses.0.source_name', 'Actor')
            ->assertJsonPath('data.bonuses.0.source_slug', 'phb:actor')
            ->assertJsonPath('data.bonuses.0.ability_code', 'CHA')
            ->assertJsonPath('data.bonuses.0.ability_name', 'Charisma')
            ->assertJsonPath('data.bonuses.0.value', 1)
            ->assertJsonPath('data.bonuses.0.is_choice', false)
            ->assertJsonPath('data.totals.CHA', 1);

        // Verify conditional fields are NOT present for feat bonuses
        expect($response->json('data.bonuses.0'))->not->toHaveKey('choice_resolved');
        expect($response->json('data.bonuses.0'))->not->toHaveKey('modifier_id');
    });

    it('calculates totals correctly with mixed sources', function () {
        $str = AbilityScore::where('code', 'STR')->first();
        $cha = AbilityScore::where('code', 'CHA')->first();

        // Race with fixed STR bonus and choice CHA bonus
        $race = Race::factory()->create(['name' => 'Half-Orc', 'full_slug' => 'phb:half-orc']);

        Modifier::create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'modifier_category' => 'ability_score',
            'ability_score_id' => $str->id,
            'value' => '2',
            'is_choice' => false,
        ]);

        $choiceModifier = Modifier::create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'modifier_category' => 'ability_score',
            'ability_score_id' => null,
            'value' => '1',
            'is_choice' => true,
            'choice_count' => 1,
        ]);

        // Feat with CHA bonus
        $feat = Feat::factory()->create(['name' => 'Actor', 'full_slug' => 'phb:actor']);

        Modifier::create([
            'reference_type' => Feat::class,
            'reference_id' => $feat->id,
            'modifier_category' => 'ability_score',
            'ability_score_id' => $cha->id,
            'value' => '1',
            'is_choice' => false,
        ]);

        $character = Character::factory()->withRace($race)->create();

        // Resolve racial choice to CHA
        CharacterAbilityScore::create([
            'character_id' => $character->id,
            'ability_score_code' => 'CHA',
            'bonus' => 1,
            'source' => 'race',
            'modifier_id' => $choiceModifier->id,
        ]);

        // Add feat
        CharacterFeature::create([
            'character_id' => $character->id,
            'feature_type' => Feat::class,
            'feature_id' => $feat->id,
            'feature_slug' => $feat->full_slug,
            'source' => 'feat',
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->public_id}/ability-bonuses");

        $response->assertOk()
            ->assertJsonCount(3, 'data.bonuses')
            ->assertJsonPath('data.totals.STR', 2)  // From race fixed
            ->assertJsonPath('data.totals.CHA', 2)  // 1 from race choice + 1 from feat
            ->assertJsonPath('data.totals.DEX', 0)
            ->assertJsonPath('data.totals.CON', 0)
            ->assertJsonPath('data.totals.INT', 0)
            ->assertJsonPath('data.totals.WIS', 0);
    });

    it('returns 404 for non-existent character', function () {
        $response = $this->getJson('/api/v1/characters/nonexistent-slug-xxxx/ability-bonuses');

        $response->assertNotFound();
    });

    it('handles subrace inheritance correctly', function () {
        $str = AbilityScore::where('code', 'STR')->first();
        $con = AbilityScore::where('code', 'CON')->first();
        $dex = AbilityScore::where('code', 'DEX')->first();

        // Parent race: Dwarf (+2 CON)
        $parentRace = Race::factory()->create(['name' => 'Dwarf', 'full_slug' => 'phb:dwarf']);

        Modifier::create([
            'reference_type' => Race::class,
            'reference_id' => $parentRace->id,
            'modifier_category' => 'ability_score',
            'ability_score_id' => $con->id,
            'value' => '2',
            'is_choice' => false,
        ]);

        // Subrace: Mountain Dwarf (+2 STR)
        $subrace = Race::factory()->create([
            'name' => 'Mountain Dwarf',
            'full_slug' => 'phb:mountain-dwarf',
            'parent_race_id' => $parentRace->id,
        ]);

        Modifier::create([
            'reference_type' => Race::class,
            'reference_id' => $subrace->id,
            'modifier_category' => 'ability_score',
            'ability_score_id' => $str->id,
            'value' => '2',
            'is_choice' => false,
        ]);

        $character = Character::factory()->withRace($subrace)->create();

        $response = $this->getJson("/api/v1/characters/{$character->public_id}/ability-bonuses");

        $response->assertOk()
            ->assertJsonCount(2, 'data.bonuses');

        // Should have both parent and subrace modifiers
        $bonuses = collect($response->json('data.bonuses'));
        $conBonus = $bonuses->firstWhere('ability_code', 'CON');
        $strBonus = $bonuses->firstWhere('ability_code', 'STR');

        expect($conBonus['value'])->toBe(2);
        expect($conBonus['source_name'])->toBe('Mountain Dwarf'); // Source is the character's race
        expect($strBonus['value'])->toBe(2);
        expect($strBonus['source_name'])->toBe('Mountain Dwarf');

        $response->assertJsonPath('data.totals.CON', 2)
            ->assertJsonPath('data.totals.STR', 2)
            ->assertJsonPath('data.totals.DEX', 0);
    });

    it('returns correct structure with race and feat bonuses combined', function () {
        $str = AbilityScore::where('code', 'STR')->first();
        $dex = AbilityScore::where('code', 'DEX')->first();
        $cha = AbilityScore::where('code', 'CHA')->first();

        // Half-Elf race: +2 CHA, +1 to two chosen abilities
        $race = Race::factory()->create(['name' => 'Half-Elf', 'full_slug' => 'phb:half-elf']);

        Modifier::create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'modifier_category' => 'ability_score',
            'ability_score_id' => $cha->id,
            'value' => '2',
            'is_choice' => false,
        ]);

        $choiceModifier = Modifier::create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'modifier_category' => 'ability_score',
            'ability_score_id' => null,
            'value' => '1',
            'is_choice' => true,
            'choice_count' => 2,
        ]);

        // Actor feat: +1 CHA
        $feat = Feat::factory()->create(['name' => 'Actor', 'full_slug' => 'phb:actor']);

        Modifier::create([
            'reference_type' => Feat::class,
            'reference_id' => $feat->id,
            'modifier_category' => 'ability_score',
            'ability_score_id' => $cha->id,
            'value' => '1',
            'is_choice' => false,
        ]);

        $character = Character::factory()->withRace($race)->create();

        // Resolve racial choices
        CharacterAbilityScore::create([
            'character_id' => $character->id,
            'ability_score_code' => 'STR',
            'bonus' => 1,
            'source' => 'race',
            'modifier_id' => $choiceModifier->id,
        ]);

        CharacterAbilityScore::create([
            'character_id' => $character->id,
            'ability_score_code' => 'DEX',
            'bonus' => 1,
            'source' => 'race',
            'modifier_id' => $choiceModifier->id,
        ]);

        // Add feat
        CharacterFeature::create([
            'character_id' => $character->id,
            'feature_type' => Feat::class,
            'feature_id' => $feat->id,
            'feature_slug' => $feat->full_slug,
            'source' => 'feat',
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->public_id}/ability-bonuses");

        $response->assertOk()
            ->assertJsonCount(4, 'data.bonuses');

        $bonuses = collect($response->json('data.bonuses'));

        // Race bonuses
        $raceBonuses = $bonuses->where('source_type', 'race');
        expect($raceBonuses)->toHaveCount(3);

        // Feat bonuses
        $featBonuses = $bonuses->where('source_type', 'feat');
        expect($featBonuses)->toHaveCount(1);

        // Verify totals
        $response->assertJsonPath('data.totals.CHA', 3)  // 2 from race + 1 from feat
            ->assertJsonPath('data.totals.STR', 1)        // 1 from race choice
            ->assertJsonPath('data.totals.DEX', 1)        // 1 from race choice
            ->assertJsonPath('data.totals.CON', 0)
            ->assertJsonPath('data.totals.INT', 0)
            ->assertJsonPath('data.totals.WIS', 0);
    });

    it('only counts resolved bonuses in totals', function () {
        $str = AbilityScore::where('code', 'STR')->first();

        $race = Race::factory()->create(['name' => 'Test Race', 'full_slug' => 'test:race']);

        // Fixed bonus
        Modifier::create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'modifier_category' => 'ability_score',
            'ability_score_id' => $str->id,
            'value' => '2',
            'is_choice' => false,
        ]);

        // Choice modifier (not resolved - should not appear in response or totals)
        Modifier::create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'modifier_category' => 'ability_score',
            'ability_score_id' => null,
            'value' => '1',
            'is_choice' => true,
            'choice_count' => 2,
        ]);

        $character = Character::factory()->withRace($race)->create();
        // Note: No CharacterAbilityScore records created, so choices are unresolved

        $response = $this->getJson("/api/v1/characters/{$character->public_id}/ability-bonuses");

        $response->assertOk()
            ->assertJsonCount(1, 'data.bonuses')  // Only the fixed bonus
            ->assertJsonPath('data.bonuses.0.ability_code', 'STR')
            ->assertJsonPath('data.bonuses.0.value', 2)
            ->assertJsonPath('data.bonuses.0.is_choice', false)
            ->assertJsonPath('data.totals.STR', 2)  // Only from resolved/fixed bonuses
            ->assertJsonPath('data.totals.DEX', 0);
    });

    it('handles multiple feats with ability bonuses', function () {
        $cha = AbilityScore::where('code', 'CHA')->first();
        $str = AbilityScore::where('code', 'STR')->first();

        // Actor feat: +1 CHA
        $actorFeat = Feat::factory()->create(['name' => 'Actor', 'full_slug' => 'phb:actor']);
        Modifier::create([
            'reference_type' => Feat::class,
            'reference_id' => $actorFeat->id,
            'modifier_category' => 'ability_score',
            'ability_score_id' => $cha->id,
            'value' => '1',
            'is_choice' => false,
        ]);

        // Athlete feat: +1 STR
        $athleteFeat = Feat::factory()->create(['name' => 'Athlete', 'full_slug' => 'phb:athlete']);
        Modifier::create([
            'reference_type' => Feat::class,
            'reference_id' => $athleteFeat->id,
            'modifier_category' => 'ability_score',
            'ability_score_id' => $str->id,
            'value' => '1',
            'is_choice' => false,
        ]);

        $character = Character::factory()->create();

        // Add both feats
        CharacterFeature::create([
            'character_id' => $character->id,
            'feature_type' => Feat::class,
            'feature_id' => $actorFeat->id,
            'feature_slug' => $actorFeat->full_slug,
            'source' => 'feat',
        ]);

        CharacterFeature::create([
            'character_id' => $character->id,
            'feature_type' => Feat::class,
            'feature_id' => $athleteFeat->id,
            'feature_slug' => $athleteFeat->full_slug,
            'source' => 'feat',
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->public_id}/ability-bonuses");

        $response->assertOk()
            ->assertJsonCount(2, 'data.bonuses');

        $bonuses = collect($response->json('data.bonuses'));
        $chaBonus = $bonuses->firstWhere('ability_code', 'CHA');
        $strBonus = $bonuses->firstWhere('ability_code', 'STR');

        expect($chaBonus['source_name'])->toBe('Actor');
        expect($chaBonus['value'])->toBe(1);
        expect($strBonus['source_name'])->toBe('Athlete');
        expect($strBonus['value'])->toBe(1);

        $response->assertJsonPath('data.totals.CHA', 1)
            ->assertJsonPath('data.totals.STR', 1);
    });

    it('returns empty totals when character has no bonuses', function () {
        $character = Character::factory()->create();

        $response = $this->getJson("/api/v1/characters/{$character->public_id}/ability-bonuses");

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'bonuses' => [],
                    'totals' => [
                        'STR' => 0,
                        'DEX' => 0,
                        'CON' => 0,
                        'INT' => 0,
                        'WIS' => 0,
                        'CHA' => 0,
                    ],
                ],
            ]);
    });
});
