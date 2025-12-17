<?php

namespace Tests\Feature\Api;

use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\CharacterSpell;
use App\Models\CharacterSpellSlot;
use App\Models\Spell;
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
}
