<?php

namespace Tests\Feature\Api;

use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\CharacterSpell;
use App\Models\CharacterSpellSlot;
use App\Models\Spell;
use App\Models\SpellEffect;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CharacterSpellTest extends TestCase
{
    use RefreshDatabase;

    // =====================
    // List Character Spells Tests
    // =====================

    #[Test]
    public function it_lists_spells_for_a_character(): void
    {
        $character = Character::factory()->create();
        $spells = Spell::factory()->count(3)->create();

        foreach ($spells as $spell) {
            CharacterSpell::create([
                'character_id' => $character->id,
                'spell_slug' => $spell->slug,
                'preparation_status' => 'known',
                'source' => 'class',
            ]);
        }

        $response = $this->getJson("/api/v1/characters/{$character->id}/spells");

        $response->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'spell', 'preparation_status', 'source'],
                ],
            ]);
    }

    #[Test]
    public function it_includes_description_and_higher_levels_in_nested_spell_object(): void
    {
        $character = Character::factory()->create();
        $spell = Spell::factory()->create([
            'description' => 'A bright streak flashes from your pointing finger.',
            'higher_levels' => 'When you cast this spell using a spell slot of 4th level or higher...',
        ]);

        CharacterSpell::create([
            'character_id' => $character->id,
            'spell_slug' => $spell->slug,
            'preparation_status' => 'known',
            'source' => 'class',
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/spells");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'spell' => [
                            'id',
                            'name',
                            'slug',
                            'level',
                            'school',
                            'casting_time',
                            'range',
                            'components',
                            'duration',
                            'concentration',
                            'ritual',
                            'description',
                            'higher_levels',
                        ],
                    ],
                ],
            ])
            ->assertJsonPath('data.0.spell.description', 'A bright streak flashes from your pointing finger.')
            ->assertJsonPath('data.0.spell.higher_levels', 'When you cast this spell using a spell slot of 4th level or higher...');
    }

    #[Test]
    public function it_returns_null_higher_levels_when_spell_has_none(): void
    {
        $character = Character::factory()->create();
        $spell = Spell::factory()->create([
            'description' => 'You create a magical bond between yourself and a willing creature.',
            'higher_levels' => null,
        ]);

        CharacterSpell::create([
            'character_id' => $character->id,
            'spell_slug' => $spell->slug,
            'preparation_status' => 'known',
            'source' => 'class',
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/spells");

        $response->assertOk()
            ->assertJsonPath('data.0.spell.description', 'You create a magical bond between yourself and a willing creature.')
            ->assertJsonPath('data.0.spell.higher_levels', null);
    }

    // =====================
    // Combat Fields Tests (Issue #756)
    // =====================

    #[Test]
    public function it_includes_damage_types_from_spell_effects(): void
    {
        $character = Character::factory()->create();
        $spell = Spell::factory()->create([
            'name' => 'Fireball',
            'description' => 'A bright streak flashes from your pointing finger to a point you choose.',
        ]);

        // Create fire damage effect
        SpellEffect::factory()->damage('Fire')->create(['spell_id' => $spell->id]);

        CharacterSpell::create([
            'character_id' => $character->id,
            'spell_slug' => $spell->slug,
            'preparation_status' => 'known',
            'source' => 'class',
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/spells");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'spell' => [
                            'damage_types',
                        ],
                    ],
                ],
            ])
            ->assertJsonPath('data.0.spell.damage_types', ['Fire']);
    }

    #[Test]
    public function it_includes_multiple_damage_types_for_spells_with_multiple_effects(): void
    {
        $character = Character::factory()->create();
        $spell = Spell::factory()->create([
            'name' => 'Chromatic Orb',
            'description' => 'You hurl a sphere of energy at a creature.',
        ]);

        // Create effects with different damage types
        SpellEffect::factory()->damage('Fire')->create(['spell_id' => $spell->id]);
        SpellEffect::factory()->damage('Cold')->create(['spell_id' => $spell->id]);

        CharacterSpell::create([
            'character_id' => $character->id,
            'spell_slug' => $spell->slug,
            'preparation_status' => 'known',
            'source' => 'class',
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/spells");

        $response->assertOk();
        $damageTypes = $response->json('data.0.spell.damage_types');
        $this->assertCount(2, $damageTypes);
        $this->assertContains('Fire', $damageTypes);
        $this->assertContains('Cold', $damageTypes);
    }

    #[Test]
    public function it_returns_empty_array_when_spell_has_no_damage_type(): void
    {
        $character = Character::factory()->create();
        $spell = Spell::factory()->create([
            'name' => 'Shield',
            'description' => 'An invisible barrier of magical force appears and protects you.',
        ]);

        CharacterSpell::create([
            'character_id' => $character->id,
            'spell_slug' => $spell->slug,
            'preparation_status' => 'known',
            'source' => 'class',
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/spells");

        $response->assertOk()
            ->assertJsonPath('data.0.spell.damage_types', []);
    }

    #[Test]
    public function it_includes_saving_throw_ability(): void
    {
        $character = Character::factory()->create();
        $spell = Spell::factory()->create([
            'name' => 'Fireball',
            'description' => 'Each creature in a 20-foot-radius sphere must make a Dexterity saving throw.',
        ]);

        // Attach DEX saving throw
        $dex = \App\Models\AbilityScore::where('code', 'DEX')->first();
        $spell->savingThrows()->attach($dex->id, [
            'save_effect' => 'half damage',
            'is_initial_save' => true,
        ]);

        CharacterSpell::create([
            'character_id' => $character->id,
            'spell_slug' => $spell->slug,
            'preparation_status' => 'known',
            'source' => 'class',
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/spells");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'spell' => [
                            'saving_throw',
                        ],
                    ],
                ],
            ])
            ->assertJsonPath('data.0.spell.saving_throw', 'DEX');
    }

    #[Test]
    public function it_returns_null_saving_throw_when_spell_has_none(): void
    {
        $character = Character::factory()->create();
        $spell = Spell::factory()->create([
            'name' => 'Magic Missile',
            'description' => 'You create three glowing darts of magical force.',
        ]);

        CharacterSpell::create([
            'character_id' => $character->id,
            'spell_slug' => $spell->slug,
            'preparation_status' => 'known',
            'source' => 'class',
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/spells");

        $response->assertOk()
            ->assertJsonPath('data.0.spell.saving_throw', null);
    }

    #[Test]
    public function it_includes_attack_type_for_ranged_spell_attack(): void
    {
        $character = Character::factory()->create();
        $spell = Spell::factory()->create([
            'name' => 'Fire Bolt',
            'description' => 'You hurl a mote of fire at a creature or object within range. Make a ranged spell attack against the target.',
        ]);

        CharacterSpell::create([
            'character_id' => $character->id,
            'spell_slug' => $spell->slug,
            'preparation_status' => 'known',
            'source' => 'class',
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/spells");

        $response->assertOk()
            ->assertJsonPath('data.0.spell.attack_type', 'ranged');
    }

    #[Test]
    public function it_includes_attack_type_for_melee_spell_attack(): void
    {
        $character = Character::factory()->create();
        $spell = Spell::factory()->create([
            'name' => 'Shocking Grasp',
            'description' => 'Lightning springs from your hand to deliver a shock. Make a melee spell attack against the target.',
        ]);

        CharacterSpell::create([
            'character_id' => $character->id,
            'spell_slug' => $spell->slug,
            'preparation_status' => 'known',
            'source' => 'class',
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/spells");

        $response->assertOk()
            ->assertJsonPath('data.0.spell.attack_type', 'melee');
    }

    #[Test]
    public function it_returns_null_attack_type_when_spell_has_no_attack(): void
    {
        $character = Character::factory()->create();
        $spell = Spell::factory()->create([
            'name' => 'Fireball',
            'description' => 'A bright streak flashes. Each creature must make a Dexterity saving throw.',
        ]);

        CharacterSpell::create([
            'character_id' => $character->id,
            'spell_slug' => $spell->slug,
            'preparation_status' => 'known',
            'source' => 'class',
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/spells");

        $response->assertOk()
            ->assertJsonPath('data.0.spell.attack_type', null);
    }

    #[Test]
    public function it_returns_empty_array_when_character_has_no_spells(): void
    {
        $character = Character::factory()->create();

        $response = $this->getJson("/api/v1/characters/{$character->id}/spells");

        $response->assertOk()
            ->assertJsonCount(0, 'data');
    }

    // =====================
    // Available Spells Tests
    // =====================

    #[Test]
    public function it_lists_available_spells_for_a_spellcaster(): void
    {
        $wizardClass = CharacterClass::factory()->spellcaster('INT')->create(['name' => 'Wizard']);
        $character = Character::factory()->withClass($wizardClass)->create();

        // Create spells and attach to wizard class
        $wizardSpells = Spell::factory()->count(3)->create(['level' => 1]);
        $wizardClass->spells()->attach($wizardSpells->pluck('id'));

        $response = $this->getJson("/api/v1/characters/{$character->id}/available-spells");

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    #[Test]
    public function it_filters_available_spells_by_level(): void
    {
        $wizardClass = CharacterClass::factory()->spellcaster('INT')->create(['name' => 'Wizard']);
        $character = Character::factory()->withClass($wizardClass)->level(5)->create();

        // Level 1 spells
        $level1Spells = Spell::factory()->count(2)->create(['level' => 1]);
        // Level 3 spells
        $level3Spells = Spell::factory()->count(2)->create(['level' => 3]);
        // Level 5 spells (too high for level 5 wizard to learn yet)
        $level5Spells = Spell::factory()->count(2)->create(['level' => 5]);

        $wizardClass->spells()->attach(
            $level1Spells->pluck('id')
                ->merge($level3Spells->pluck('id'))
                ->merge($level5Spells->pluck('id'))
        );

        // A level 5 wizard can cast up to 3rd level spells
        $response = $this->getJson("/api/v1/characters/{$character->id}/available-spells?max_level=3");

        $response->assertOk()
            ->assertJsonCount(4, 'data'); // Only level 1 and 3 spells
    }

    #[Test]
    public function it_excludes_already_known_spells_from_available(): void
    {
        $wizardClass = CharacterClass::factory()->spellcaster('INT')->create(['name' => 'Wizard']);
        $character = Character::factory()->withClass($wizardClass)->create();

        $spells = Spell::factory()->count(5)->create(['level' => 1]);
        $wizardClass->spells()->attach($spells->pluck('id'));

        // Character already knows 2 spells
        CharacterSpell::create([
            'character_id' => $character->id,
            'spell_slug' => $spells[0]->slug,
            'preparation_status' => 'known',
            'source' => 'class',
        ]);
        CharacterSpell::create([
            'character_id' => $character->id,
            'spell_slug' => $spells[1]->slug,
            'preparation_status' => 'known',
            'source' => 'class',
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/available-spells");

        $response->assertOk()
            ->assertJsonCount(3, 'data'); // 5 total - 2 known = 3 available
    }

    #[Test]
    public function it_includes_known_spells_when_include_known_parameter_is_true(): void
    {
        $wizardClass = CharacterClass::factory()->spellcaster('INT')->create(['name' => 'Wizard']);
        $character = Character::factory()->withClass($wizardClass)->create();

        $spells = Spell::factory()->count(5)->create(['level' => 1]);
        $wizardClass->spells()->attach($spells->pluck('id'));

        // Character already knows 2 spells
        CharacterSpell::create([
            'character_id' => $character->id,
            'spell_slug' => $spells[0]->slug,
            'preparation_status' => 'known',
            'source' => 'class',
        ]);
        CharacterSpell::create([
            'character_id' => $character->id,
            'spell_slug' => $spells[1]->slug,
            'preparation_status' => 'known',
            'source' => 'class',
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/available-spells?include_known=true");

        $response->assertOk()
            ->assertJsonCount(5, 'data'); // All 5 spells including already known
    }

    #[Test]
    public function it_filters_available_spells_by_min_level(): void
    {
        $wizardClass = CharacterClass::factory()->spellcaster('INT')->create(['name' => 'Wizard']);
        $character = Character::factory()->withClass($wizardClass)->level(5)->create();

        // Cantrips (level 0)
        $cantrips = Spell::factory()->count(2)->create(['level' => 0]);
        // Level 1 spells
        $level1Spells = Spell::factory()->count(3)->create(['level' => 1]);
        // Level 2 spells
        $level2Spells = Spell::factory()->count(2)->create(['level' => 2]);

        $wizardClass->spells()->attach(
            $cantrips->pluck('id')
                ->merge($level1Spells->pluck('id'))
                ->merge($level2Spells->pluck('id'))
        );

        // min_level=1 should exclude cantrips (level 0)
        $response = $this->getJson("/api/v1/characters/{$character->id}/available-spells?min_level=1");

        $response->assertOk()
            ->assertJsonCount(5, 'data'); // 3 level-1 + 2 level-2, no cantrips

        // Verify no cantrips in response
        $slugs = collect($response->json('data'))->pluck('slug');
        foreach ($cantrips as $cantrip) {
            $this->assertNotContains($cantrip->slug, $slugs);
        }
    }

    #[Test]
    public function it_filters_available_spells_by_min_and_max_level(): void
    {
        $wizardClass = CharacterClass::factory()->spellcaster('INT')->create(['name' => 'Wizard']);
        $character = Character::factory()->withClass($wizardClass)->level(5)->create();

        // Cantrips (level 0)
        $cantrips = Spell::factory()->count(2)->create(['level' => 0]);
        // Level 1 spells
        $level1Spells = Spell::factory()->count(3)->create(['level' => 1]);
        // Level 2 spells
        $level2Spells = Spell::factory()->count(2)->create(['level' => 2]);
        // Level 3 spells
        $level3Spells = Spell::factory()->count(2)->create(['level' => 3]);

        $wizardClass->spells()->attach(
            $cantrips->pluck('id')
                ->merge($level1Spells->pluck('id'))
                ->merge($level2Spells->pluck('id'))
                ->merge($level3Spells->pluck('id'))
        );

        // min_level=1 max_level=2 should return only level 1-2 spells
        $response = $this->getJson("/api/v1/characters/{$character->id}/available-spells?min_level=1&max_level=2");

        $response->assertOk()
            ->assertJsonCount(5, 'data'); // 3 level-1 + 2 level-2

        // Verify levels in response
        $levels = collect($response->json('data'))->pluck('level')->unique()->values();
        $this->assertEquals([1, 2], $levels->sort()->values()->all());
    }

    #[Test]
    public function it_combines_max_level_and_include_known_parameters(): void
    {
        $wizardClass = CharacterClass::factory()->spellcaster('INT')->create(['name' => 'Wizard']);
        $character = Character::factory()->withClass($wizardClass)->level(5)->create();

        // Create spells at different levels
        $level1Spells = Spell::factory()->count(3)->create(['level' => 1]);
        $level3Spells = Spell::factory()->count(2)->create(['level' => 3]);
        $wizardClass->spells()->attach(
            $level1Spells->pluck('id')->merge($level3Spells->pluck('id'))
        );

        // Character knows 1 level-1 spell and 1 level-3 spell
        CharacterSpell::create([
            'character_id' => $character->id,
            'spell_slug' => $level1Spells[0]->slug,
            'preparation_status' => 'known',
            'source' => 'class',
        ]);
        CharacterSpell::create([
            'character_id' => $character->id,
            'spell_slug' => $level3Spells[0]->slug,
            'preparation_status' => 'known',
            'source' => 'class',
        ]);

        // Without include_known: should get 2 level-1 spells (3 - 1 known)
        $response = $this->getJson("/api/v1/characters/{$character->id}/available-spells?max_level=1");
        $response->assertOk()->assertJsonCount(2, 'data');

        // With include_known: should get all 3 level-1 spells
        $response = $this->getJson("/api/v1/characters/{$character->id}/available-spells?max_level=1&include_known=true");
        $response->assertOk()->assertJsonCount(3, 'data');
    }

    // =====================
    // Learn Spell Tests
    // =====================

    #[Test]
    public function it_learns_a_spell_on_class_list(): void
    {
        $wizardClass = CharacterClass::factory()->spellcaster('INT')->create(['name' => 'Wizard']);
        $character = Character::factory()->withClass($wizardClass)->create();
        $spell = Spell::factory()->create(['level' => 1]);
        $wizardClass->spells()->attach($spell->id);

        $response = $this->postJson("/api/v1/characters/{$character->id}/spells", [
            'spell_slug' => $spell->slug,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.spell.id', $spell->id)
            ->assertJsonPath('data.preparation_status', 'known');

        $this->assertDatabaseHas('character_spells', [
            'character_id' => $character->id,
            'spell_slug' => $spell->slug,
        ]);
    }

    #[Test]
    public function it_rejects_learning_spell_not_on_class_list(): void
    {
        $wizardClass = CharacterClass::factory()->spellcaster('INT')->create(['name' => 'Wizard']);
        $clericClass = CharacterClass::factory()->spellcaster('WIS')->create(['name' => 'Cleric']);
        $character = Character::factory()->withClass($wizardClass)->create();

        // Create a cleric-only spell
        $clericSpell = Spell::factory()->create(['name' => 'Cure Wounds', 'level' => 1]);
        $clericClass->spells()->attach($clericSpell->id);

        $response = $this->postJson("/api/v1/characters/{$character->id}/spells", [
            'spell_slug' => $clericSpell->slug,
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('message', "The spell 'Cure Wounds' is not available for this character's class.");
    }

    #[Test]
    public function it_rejects_learning_spell_too_high_level(): void
    {
        $wizardClass = CharacterClass::factory()->spellcaster('INT')->create(['name' => 'Wizard']);
        $character = Character::factory()->withClass($wizardClass)->level(1)->create();

        // Level 5 spell - too high for level 1 wizard
        $spell = Spell::factory()->create(['level' => 5]);
        $wizardClass->spells()->attach($spell->id);

        $response = $this->postJson("/api/v1/characters/{$character->id}/spells", [
            'spell_slug' => $spell->slug,
        ]);

        $response->assertUnprocessable();
    }

    #[Test]
    public function it_rejects_learning_already_known_spell(): void
    {
        $wizardClass = CharacterClass::factory()->spellcaster('INT')->create(['name' => 'Wizard']);
        $character = Character::factory()->withClass($wizardClass)->create();
        $spell = Spell::factory()->create(['level' => 1]);
        $wizardClass->spells()->attach($spell->id);

        // Character already knows this spell
        CharacterSpell::create([
            'character_id' => $character->id,
            'spell_slug' => $spell->slug,
            'preparation_status' => 'known',
            'source' => 'class',
        ]);

        $response = $this->postJson("/api/v1/characters/{$character->id}/spells", [
            'spell_slug' => $spell->slug,
        ]);

        $response->assertUnprocessable();
    }

    // =====================
    // Remove Spell Tests
    // =====================

    #[Test]
    public function it_removes_a_learned_spell(): void
    {
        $character = Character::factory()->create();
        $spell = Spell::factory()->create();

        $characterSpell = CharacterSpell::create([
            'character_id' => $character->id,
            'spell_slug' => $spell->slug,
            'preparation_status' => 'known',
            'source' => 'class',
        ]);

        $response = $this->deleteJson("/api/v1/characters/{$character->id}/spells/{$spell->slug}");

        $response->assertNoContent();

        $this->assertDatabaseMissing('character_spells', [
            'character_id' => $character->id,
            'spell_slug' => $spell->slug,
        ]);
    }

    #[Test]
    public function it_returns_404_when_removing_spell_not_known(): void
    {
        $character = Character::factory()->create();
        $spell = Spell::factory()->create();

        $response = $this->deleteJson("/api/v1/characters/{$character->id}/spells/{$spell->slug}");

        $response->assertNotFound();
    }

    // =====================
    // Prepare/Unprepare Spell Tests
    // =====================

    #[Test]
    public function it_prepares_a_known_spell(): void
    {
        $character = Character::factory()->create();
        $spell = Spell::factory()->create(['level' => 1]);

        CharacterSpell::create([
            'character_id' => $character->id,
            'spell_slug' => $spell->slug,
            'preparation_status' => 'known',
            'source' => 'class',
        ]);

        $response = $this->patchJson("/api/v1/characters/{$character->id}/spells/{$spell->slug}/prepare");

        $response->assertOk()
            ->assertJsonPath('data.preparation_status', 'prepared');

        $this->assertDatabaseHas('character_spells', [
            'character_id' => $character->id,
            'spell_slug' => $spell->slug,
            'preparation_status' => 'prepared',
        ]);
    }

    #[Test]
    public function it_unprepares_a_prepared_spell(): void
    {
        $character = Character::factory()->create();
        $spell = Spell::factory()->create(['level' => 1]);

        CharacterSpell::create([
            'character_id' => $character->id,
            'spell_slug' => $spell->slug,
            'preparation_status' => 'prepared',
            'source' => 'class',
        ]);

        $response = $this->patchJson("/api/v1/characters/{$character->id}/spells/{$spell->slug}/unprepare");

        $response->assertOk()
            ->assertJsonPath('data.preparation_status', 'known');

        $this->assertDatabaseHas('character_spells', [
            'character_id' => $character->id,
            'spell_slug' => $spell->slug,
            'preparation_status' => 'known',
        ]);
    }

    #[Test]
    public function it_cannot_unprepare_always_prepared_spell(): void
    {
        $character = Character::factory()->create();
        $spell = Spell::factory()->create(['level' => 1]);

        CharacterSpell::create([
            'character_id' => $character->id,
            'spell_slug' => $spell->slug,
            'preparation_status' => 'always_prepared',
            'source' => 'class', // Always-prepared spells come from class features/domain
        ]);

        $response = $this->patchJson("/api/v1/characters/{$character->id}/spells/{$spell->slug}/unprepare");

        $response->assertUnprocessable();
    }

    #[Test]
    public function it_cannot_prepare_cantrip(): void
    {
        $character = Character::factory()->create();
        $cantrip = Spell::factory()->cantrip()->create();

        CharacterSpell::create([
            'character_id' => $character->id,
            'spell_slug' => $cantrip->slug,
            'preparation_status' => 'known',
            'source' => 'class',
        ]);

        $response = $this->patchJson("/api/v1/characters/{$character->id}/spells/{$cantrip->slug}/prepare");

        $response->assertUnprocessable();
    }

    // =====================
    // Spell Slot & Preparation Limit Tests
    // =====================

    #[Test]
    public function it_returns_spell_slots_for_character(): void
    {
        $wizardClass = CharacterClass::factory()->spellcaster('INT')->create(['name' => 'Wizard']);
        $character = Character::factory()
            ->withClass($wizardClass)
            ->withAbilityScores(['intelligence' => 16])
            ->level(3)
            ->create();

        $response = $this->getJson("/api/v1/characters/{$character->id}/spell-slots");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'slots',
                    'preparation_limit',
                    'prepared_count',
                ],
            ]);
    }

    #[Test]
    public function it_returns_consolidated_spell_slots_with_usage_data(): void
    {
        $wizardClass = CharacterClass::factory()->spellcaster('INT')->create(['name' => 'Wizard']);
        $character = Character::factory()
            ->withClass($wizardClass)
            ->withAbilityScores(['intelligence' => 16])
            ->level(3)
            ->create();

        // Create tracked spell slot usage
        CharacterSpellSlot::create([
            'character_id' => $character->id,
            'spell_level' => 1,
            'max_slots' => 4,
            'used_slots' => 2,
            'slot_type' => \App\Enums\SpellSlotType::STANDARD,
        ]);

        CharacterSpellSlot::create([
            'character_id' => $character->id,
            'spell_level' => 2,
            'max_slots' => 2,
            'used_slots' => 1,
            'slot_type' => \App\Enums\SpellSlotType::STANDARD,
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/spell-slots");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'slots' => [
                        '1' => ['total', 'spent', 'available'],
                        '2' => ['total', 'spent', 'available'],
                    ],
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
    public function it_returns_warlock_pact_magic_slots_with_usage_data(): void
    {
        $warlockClass = CharacterClass::factory()->spellcaster('CHA')->create(['name' => 'Warlock']);
        $character = Character::factory()
            ->withClass($warlockClass)
            ->withAbilityScores(['charisma' => 16])
            ->level(5)
            ->create();

        // Create pact magic slot usage
        CharacterSpellSlot::create([
            'character_id' => $character->id,
            'spell_level' => 3,
            'max_slots' => 2,
            'used_slots' => 1,
            'slot_type' => \App\Enums\SpellSlotType::PACT_MAGIC,
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/spell-slots");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'slots',
                    'pact_magic' => ['level', 'total', 'spent', 'available'],
                    'preparation_limit',
                    'prepared_count',
                ],
            ])
            ->assertJsonPath('data.slots', []) // Warlocks have no standard slots
            ->assertJsonPath('data.pact_magic.level', 3)
            ->assertJsonPath('data.pact_magic.total', 2)
            ->assertJsonPath('data.pact_magic.spent', 1)
            ->assertJsonPath('data.pact_magic.available', 1);
    }

    #[Test]
    public function it_returns_zero_spent_when_no_usage_tracked(): void
    {
        $wizardClass = CharacterClass::factory()->spellcaster('INT')->create(['name' => 'Wizard']);
        $character = Character::factory()
            ->withClass($wizardClass)
            ->withAbilityScores(['intelligence' => 16])
            ->level(3)
            ->create();

        // No CharacterSpellSlot records exist - all slots should show as available

        $response = $this->getJson("/api/v1/characters/{$character->id}/spell-slots");

        $response->assertOk();

        $slots = $response->json('data.slots');

        // Verify all slots show 0 spent and full availability
        foreach ($slots as $level => $slotData) {
            $this->assertEquals(0, $slotData['spent'], "Level $level should have 0 spent");
            $this->assertEquals($slotData['total'], $slotData['available'], "Level $level should be fully available");
        }
    }

    #[Test]
    public function it_rejects_preparing_when_at_preparation_limit(): void
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
        // Learn and prepare the maximum number of spells
        foreach ($spells->take(2) as $spell) {
            CharacterSpell::create([
                'character_id' => $character->id,
                'spell_slug' => $spell->slug,
                'preparation_status' => 'prepared',
                'source' => 'class',
            ]);
        }

        // Learn but don't prepare a third spell
        CharacterSpell::create([
            'character_id' => $character->id,
            'spell_slug' => $spells[2]->slug,
            'preparation_status' => 'known',
            'source' => 'class',
        ]);

        // Try to prepare beyond the limit
        $response = $this->patchJson("/api/v1/characters/{$character->id}/spells/{$spells[2]->slug}/prepare");

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'Preparation limit reached. Unprepare a spell first.');
    }

    // =====================
    // Multiclass Spell Tests (Issue #731)
    // =====================

    #[Test]
    public function multiclass_can_learn_spell_from_secondary_class_with_class_slug(): void
    {
        // Create Wizard (spellbook) and Cleric (prepared) classes
        $wizardClass = CharacterClass::factory()->spellbookCaster('INT')->create(['name' => 'Wizard']);
        $clericClass = CharacterClass::factory()->preparedCaster('WIS')->create(['name' => 'Cleric']);

        // Create multiclass character: Wizard 5 (primary) / Cleric 5
        $character = Character::factory()
            ->withAbilityScores(['intelligence' => 16, 'wisdom' => 16])
            ->withClass($wizardClass, 5)
            ->withClass($clericClass, 5)
            ->create();

        // Create a cleric spell and attach to cleric class
        $clericSpell = Spell::factory()->create(['name' => 'Aid', 'level' => 2]);
        $clericClass->spells()->attach($clericSpell->id);

        // Try to learn the cleric spell with class_slug specified
        $response = $this->postJson("/api/v1/characters/{$character->id}/spells", [
            'spell_slug' => $clericSpell->slug,
            'class_slug' => $clericClass->slug,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.spell.id', $clericSpell->id)
            ->assertJsonPath('data.class_slug', $clericClass->slug);

        $this->assertDatabaseHas('character_spells', [
            'character_id' => $character->id,
            'spell_slug' => $clericSpell->slug,
            'class_slug' => $clericClass->slug,
        ]);
    }

    #[Test]
    public function multiclass_prepared_caster_can_prepare_spell_from_class_list(): void
    {
        // Create Wizard (spellbook) and Cleric (prepared) classes
        $wizardClass = CharacterClass::factory()->spellbookCaster('INT')->create(['name' => 'Wizard']);
        $clericClass = CharacterClass::factory()->preparedCaster('WIS')->create(['name' => 'Cleric']);

        // Create multiclass character: Wizard 5 (primary) / Cleric 5
        $character = Character::factory()
            ->withAbilityScores(['intelligence' => 16, 'wisdom' => 16])
            ->withClass($wizardClass, 5)
            ->withClass($clericClass, 5)
            ->create();

        // Create a cleric spell and attach to cleric class
        $clericSpell = Spell::factory()->create(['name' => 'Aid', 'level' => 2]);
        $clericClass->spells()->attach($clericSpell->id);

        // Cleric is a prepared caster - should be able to prepare directly from class list
        // without first "learning" the spell (auto-add via prepared_from_list)
        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/spells/{$clericSpell->slug}/prepare",
            ['class_slug' => $clericClass->slug]
        );

        $response->assertOk()
            ->assertJsonPath('data.spell.id', $clericSpell->id)
            ->assertJsonPath('data.preparation_status', 'prepared')
            ->assertJsonPath('data.source', 'prepared_from_list');

        $this->assertDatabaseHas('character_spells', [
            'character_id' => $character->id,
            'spell_slug' => $clericSpell->slug,
            'preparation_status' => 'prepared',
            'source' => 'prepared_from_list',
            'class_slug' => $clericClass->slug,
        ]);
    }

    #[Test]
    public function multiclass_rejects_spell_from_class_character_does_not_have(): void
    {
        // Create Wizard and Cleric classes
        $wizardClass = CharacterClass::factory()->spellbookCaster('INT')->create(['name' => 'Wizard']);
        $clericClass = CharacterClass::factory()->preparedCaster('WIS')->create(['name' => 'Cleric']);

        // Create single-class Wizard character (NO cleric levels)
        $character = Character::factory()
            ->withAbilityScores(['intelligence' => 16])
            ->withClass($wizardClass, 5)
            ->create();

        // Create a cleric spell
        $clericSpell = Spell::factory()->create(['name' => 'Aid', 'level' => 2]);
        $clericClass->spells()->attach($clericSpell->id);

        // Try to learn cleric spell with class_slug - should fail as character has no cleric levels
        $response = $this->postJson("/api/v1/characters/{$character->id}/spells", [
            'spell_slug' => $clericSpell->slug,
            'class_slug' => $clericClass->slug,
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('message', "The spell 'Aid' is not available for this character's class.");
    }

    #[Test]
    public function multiclass_available_spells_returns_class_specific_spells(): void
    {
        // Create Wizard and Cleric classes
        $wizardClass = CharacterClass::factory()->spellbookCaster('INT')->create(['name' => 'Wizard']);
        $clericClass = CharacterClass::factory()->preparedCaster('WIS')->create(['name' => 'Cleric']);

        // Create multiclass character: Wizard 5 (primary) / Cleric 5
        $character = Character::factory()
            ->withAbilityScores(['intelligence' => 16, 'wisdom' => 16])
            ->withClass($wizardClass, 5)
            ->withClass($clericClass, 5)
            ->create();

        // Create wizard and cleric spells
        $wizardSpells = Spell::factory()->count(3)->create(['level' => 1]);
        $clericSpells = Spell::factory()->count(5)->create(['level' => 1]);

        $wizardClass->spells()->attach($wizardSpells->pluck('id'));
        $clericClass->spells()->attach($clericSpells->pluck('id'));

        // Without class filter - should return primary class (wizard) spells
        $response = $this->getJson("/api/v1/characters/{$character->id}/available-spells");
        $response->assertOk()->assertJsonCount(3, 'data');

        // With class filter - should return cleric spells
        $response = $this->getJson("/api/v1/characters/{$character->id}/available-spells?class={$clericClass->slug}");
        $response->assertOk()->assertJsonCount(5, 'data');
    }

    #[Test]
    public function multiclass_respects_class_specific_max_spell_level(): void
    {
        // Create Wizard and Cleric classes
        $wizardClass = CharacterClass::factory()->spellbookCaster('INT')->create(['name' => 'Wizard']);
        $clericClass = CharacterClass::factory()->preparedCaster('WIS')->create(['name' => 'Cleric']);

        // Create multiclass character: Wizard 7 (primary) / Cleric 3
        // Wizard 7 can cast up to 4th level spells
        // Cleric 3 can cast up to 2nd level spells
        $character = Character::factory()
            ->withAbilityScores(['intelligence' => 16, 'wisdom' => 16])
            ->withClass($wizardClass, 7)
            ->withClass($clericClass, 3)
            ->create();

        // Create cleric spells at different levels
        $level2ClericSpell = Spell::factory()->create(['name' => 'Aid', 'level' => 2]);
        $level3ClericSpell = Spell::factory()->create(['name' => 'Revivify', 'level' => 3]);

        $clericClass->spells()->attach([$level2ClericSpell->id, $level3ClericSpell->id]);

        // Should be able to learn level 2 cleric spell (Cleric 3 can cast 2nd level)
        $response = $this->postJson("/api/v1/characters/{$character->id}/spells", [
            'spell_slug' => $level2ClericSpell->slug,
            'class_slug' => $clericClass->slug,
        ]);
        $response->assertCreated();

        // Should NOT be able to learn level 3 cleric spell (Cleric 3 cannot cast 3rd level)
        $response = $this->postJson("/api/v1/characters/{$character->id}/spells", [
            'spell_slug' => $level3ClericSpell->slug,
            'class_slug' => $clericClass->slug,
        ]);
        $response->assertUnprocessable();
    }

    // =====================
    // Scaled Cantrip Effects Tests (Issue #785)
    // =====================

    #[Test]
    public function it_returns_scaled_effects_for_cantrip_at_level_3(): void
    {
        $character = Character::factory()->level(3)->create();
        $cantrip = Spell::factory()->cantrip()->create(['name' => 'Fire Bolt']);

        // Create cantrip scaling tiers (like Fire Bolt)
        $this->createCantripScalingEffects($cantrip, 'Fire');

        CharacterSpell::create([
            'character_id' => $character->id,
            'spell_slug' => $cantrip->slug,
            'preparation_status' => 'known',
            'source' => 'class',
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/spells");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'spell' => ['id', 'name'],
                        'scaled_effects',
                    ],
                ],
            ]);

        // Level 3 character should get tier 0 (1d10)
        $scaledEffects = $response->json('data.0.scaled_effects');
        $this->assertCount(1, $scaledEffects);
        $this->assertEquals('1d10', $scaledEffects[0]['dice_formula']);
        $this->assertEquals('Fire', $scaledEffects[0]['damage_type']);
    }

    #[Test]
    public function it_returns_scaled_effects_for_cantrip_at_level_5(): void
    {
        $character = Character::factory()->level(5)->create();
        $cantrip = Spell::factory()->cantrip()->create(['name' => 'Fire Bolt']);

        $this->createCantripScalingEffects($cantrip, 'Fire');

        CharacterSpell::create([
            'character_id' => $character->id,
            'spell_slug' => $cantrip->slug,
            'preparation_status' => 'known',
            'source' => 'class',
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/spells");

        $response->assertOk();

        // Level 5 character should get tier 5 (2d10)
        $scaledEffects = $response->json('data.0.scaled_effects');
        $this->assertCount(1, $scaledEffects);
        $this->assertEquals('2d10', $scaledEffects[0]['dice_formula']);
    }

    #[Test]
    public function it_returns_scaled_effects_for_cantrip_at_level_11(): void
    {
        $character = Character::factory()->level(11)->create();
        $cantrip = Spell::factory()->cantrip()->create(['name' => 'Fire Bolt']);

        $this->createCantripScalingEffects($cantrip, 'Fire');

        CharacterSpell::create([
            'character_id' => $character->id,
            'spell_slug' => $cantrip->slug,
            'preparation_status' => 'known',
            'source' => 'class',
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/spells");

        $response->assertOk();

        // Level 11 character should get tier 11 (3d10)
        $scaledEffects = $response->json('data.0.scaled_effects');
        $this->assertCount(1, $scaledEffects);
        $this->assertEquals('3d10', $scaledEffects[0]['dice_formula']);
    }

    #[Test]
    public function it_returns_scaled_effects_for_cantrip_at_level_17(): void
    {
        $character = Character::factory()->level(17)->create();
        $cantrip = Spell::factory()->cantrip()->create(['name' => 'Fire Bolt']);

        $this->createCantripScalingEffects($cantrip, 'Fire');

        CharacterSpell::create([
            'character_id' => $character->id,
            'spell_slug' => $cantrip->slug,
            'preparation_status' => 'known',
            'source' => 'class',
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/spells");

        $response->assertOk();

        // Level 17 character should get tier 17 (4d10)
        $scaledEffects = $response->json('data.0.scaled_effects');
        $this->assertCount(1, $scaledEffects);
        $this->assertEquals('4d10', $scaledEffects[0]['dice_formula']);
    }

    #[Test]
    public function it_returns_scaled_effects_at_boundary_level_10(): void
    {
        $character = Character::factory()->level(10)->create();
        $cantrip = Spell::factory()->cantrip()->create(['name' => 'Fire Bolt']);

        $this->createCantripScalingEffects($cantrip, 'Fire');

        CharacterSpell::create([
            'character_id' => $character->id,
            'spell_slug' => $cantrip->slug,
            'preparation_status' => 'known',
            'source' => 'class',
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/spells");

        $response->assertOk();

        // Level 10 is still tier 5 (hasn't reached 11 yet)
        $scaledEffects = $response->json('data.0.scaled_effects');
        $this->assertCount(1, $scaledEffects);
        $this->assertEquals('2d10', $scaledEffects[0]['dice_formula']);
    }

    #[Test]
    public function it_returns_empty_scaled_effects_for_non_scaling_spell(): void
    {
        $character = Character::factory()->level(5)->create();
        $spell = Spell::factory()->create(['name' => 'Shield', 'level' => 1]);

        // No scaling effects for this spell

        CharacterSpell::create([
            'character_id' => $character->id,
            'spell_slug' => $spell->slug,
            'preparation_status' => 'known',
            'source' => 'class',
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/spells");

        $response->assertOk();
        $scaledEffects = $response->json('data.0.scaled_effects');
        $this->assertEmpty($scaledEffects);
    }

    #[Test]
    public function it_returns_scaled_effects_for_cantrip_without_damage_type(): void
    {
        $character = Character::factory()->level(5)->create();
        $cantrip = Spell::factory()->cantrip()->create(['name' => 'Spare the Dying']);

        // Cantrip with no damage type (like utility cantrips)
        // Should return empty scaled_effects

        CharacterSpell::create([
            'character_id' => $character->id,
            'spell_slug' => $cantrip->slug,
            'preparation_status' => 'known',
            'source' => 'class',
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/spells");

        $response->assertOk();
        $scaledEffects = $response->json('data.0.scaled_effects');
        $this->assertEmpty($scaledEffects);
    }

    /**
     * Create cantrip scaling effect tiers like Fire Bolt.
     */
    private function createCantripScalingEffects(Spell $spell, string $damageTypeName): void
    {
        $damageType = \App\Models\DamageType::where('name', $damageTypeName)->first();

        $tiers = [
            ['level' => 0, 'dice' => '1d10'],
            ['level' => 5, 'dice' => '2d10'],
            ['level' => 11, 'dice' => '3d10'],
            ['level' => 17, 'dice' => '4d10'],
        ];

        foreach ($tiers as $tier) {
            SpellEffect::create([
                'spell_id' => $spell->id,
                'effect_type' => 'damage',
                'description' => "{$damageTypeName} Damage",
                'dice_formula' => $tier['dice'],
                'scaling_type' => 'character_level',
                'min_character_level' => $tier['level'],
                'damage_type_id' => $damageType->id,
            ]);
        }
    }
}
