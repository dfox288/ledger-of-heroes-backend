<?php

namespace Tests\Feature\Api;

use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\CharacterSpell;
use App\Models\Spell;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests for prepared caster spell preparation from class list.
 *
 * Prepared casters (Cleric, Druid, Paladin, Artificer) can prepare any spell
 * from their class list without first "learning" it. This is different from
 * Wizards (who must copy spells to their spellbook) and known casters
 * (Bard, Sorcerer, etc. who have fixed known spells).
 *
 * @see https://github.com/dfox288/ledger-of-heroes/issues/727
 */
class PreparedCasterSpellPreparationTest extends TestCase
{
    use RefreshDatabase;

    // =====================
    // Prepare from Class List Tests
    // =====================

    #[Test]
    public function it_prepares_spell_from_class_list_for_prepared_caster(): void
    {
        // Arrange: Create a prepared caster (Cleric-style) with a spell on their list
        $clericClass = CharacterClass::factory()->preparedCaster('WIS')->create(['name' => 'Cleric']);
        $spell = Spell::factory()->create(['level' => 1]);
        $clericClass->spells()->attach($spell->id);

        $character = Character::factory()
            ->withClass($clericClass)
            ->withAbilityScores(['wisdom' => 14]) // +2 modifier
            ->level(1)
            ->create();

        // Act: Prepare the spell (not in character_spells yet)
        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/spells/{$spell->slug}/prepare"
        );

        // Assert: Spell is now prepared with source 'prepared_from_list'
        // Returns 200 OK (prepare action succeeded)
        $response->assertOk()
            ->assertJsonPath('data.is_prepared', true)
            ->assertJsonPath('data.source', 'prepared_from_list');

        $this->assertDatabaseHas('character_spells', [
            'character_id' => $character->id,
            'spell_slug' => $spell->slug,
            'preparation_status' => 'prepared',
            'source' => 'prepared_from_list',
        ]);
    }

    #[Test]
    public function it_rejects_cantrip_preparation_for_prepared_caster(): void
    {
        $clericClass = CharacterClass::factory()->preparedCaster('WIS')->create(['name' => 'Cleric']);
        $cantrip = Spell::factory()->cantrip()->create();
        $clericClass->spells()->attach($cantrip->id);

        $character = Character::factory()
            ->withClass($clericClass)
            ->withAbilityScores(['wisdom' => 14])
            ->level(1)
            ->create();

        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/spells/{$cantrip->slug}/prepare"
        );

        $response->assertUnprocessable();

        $this->assertDatabaseMissing('character_spells', [
            'character_id' => $character->id,
            'spell_slug' => $cantrip->slug,
        ]);
    }

    #[Test]
    public function it_rejects_spell_not_on_class_list_for_prepared_caster(): void
    {
        $clericClass = CharacterClass::factory()->preparedCaster('WIS')->create(['name' => 'Cleric']);
        $fireball = Spell::factory()->create(['level' => 3, 'name' => 'Fireball']);
        // Note: NOT attaching fireball to cleric's spell list

        $character = Character::factory()
            ->withClass($clericClass)
            ->withAbilityScores(['wisdom' => 16])
            ->level(5)
            ->create();

        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/spells/{$fireball->slug}/prepare"
        );

        $response->assertUnprocessable();

        $this->assertDatabaseMissing('character_spells', [
            'character_id' => $character->id,
            'spell_slug' => $fireball->slug,
        ]);
    }

    #[Test]
    public function it_rejects_spell_level_too_high_for_prepared_caster(): void
    {
        $clericClass = CharacterClass::factory()->preparedCaster('WIS')->create(['name' => 'Cleric']);
        $revivify = Spell::factory()->create(['level' => 3, 'name' => 'Revivify']);
        $clericClass->spells()->attach($revivify->id);

        // Level 1 cleric can only cast 1st level spells
        $character = Character::factory()
            ->withClass($clericClass)
            ->withAbilityScores(['wisdom' => 16])
            ->level(1)
            ->create();

        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/spells/{$revivify->slug}/prepare"
        );

        $response->assertUnprocessable();

        $this->assertDatabaseMissing('character_spells', [
            'character_id' => $character->id,
            'spell_slug' => $revivify->slug,
        ]);
    }

    #[Test]
    public function it_rejects_when_preparation_limit_reached_for_prepared_caster(): void
    {
        $clericClass = CharacterClass::factory()->preparedCaster('WIS')->create(['name' => 'Cleric']);
        $spells = Spell::factory()->count(5)->create(['level' => 1]);
        $clericClass->spells()->attach($spells->pluck('id'));

        // Level 1 cleric with +1 WIS modifier = prep limit of 2
        $character = Character::factory()
            ->withClass($clericClass)
            ->withAbilityScores(['wisdom' => 12]) // +1 modifier
            ->level(1)
            ->create();

        // Prepare 2 spells (the limit)
        foreach ($spells->take(2) as $spell) {
            CharacterSpell::create([
                'character_id' => $character->id,
                'spell_slug' => $spell->slug,
                'preparation_status' => 'prepared',
                'source' => 'prepared_from_list',
            ]);
        }

        // Try to prepare a 3rd spell from class list
        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/spells/{$spells[2]->slug}/prepare"
        );

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'Preparation limit reached. Unprepare a spell first.');
    }

    #[Test]
    public function it_does_not_auto_add_for_known_casters(): void
    {
        $bardClass = CharacterClass::factory()->knownCaster('CHA')->create(['name' => 'Bard']);
        $spell = Spell::factory()->create(['level' => 1]);
        $bardClass->spells()->attach($spell->id);

        $character = Character::factory()
            ->withClass($bardClass)
            ->withAbilityScores(['charisma' => 14])
            ->level(1)
            ->create();

        // Bard tries to prepare a spell not in character_spells
        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/spells/{$spell->slug}/prepare"
        );

        // Returns 404 - spell not found in character's repertoire
        $response->assertNotFound();

        $this->assertDatabaseMissing('character_spells', [
            'character_id' => $character->id,
            'spell_slug' => $spell->slug,
        ]);
    }

    #[Test]
    public function it_does_not_auto_add_for_spellbook_casters(): void
    {
        $wizardClass = CharacterClass::factory()->spellbookCaster('INT')->create(['name' => 'Wizard']);
        $spell = Spell::factory()->create(['level' => 1]);
        $wizardClass->spells()->attach($spell->id);

        $character = Character::factory()
            ->withClass($wizardClass)
            ->withAbilityScores(['intelligence' => 14])
            ->level(1)
            ->create();

        // Wizard tries to prepare a spell not in their spellbook (character_spells)
        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/spells/{$spell->slug}/prepare"
        );

        // Returns 404 - spell not found in wizard's spellbook
        $response->assertNotFound();

        $this->assertDatabaseMissing('character_spells', [
            'character_id' => $character->id,
            'spell_slug' => $spell->slug,
        ]);
    }

    // =====================
    // Unprepare Tests (delete prepared_from_list)
    // =====================

    #[Test]
    public function it_deletes_prepared_from_list_spell_on_unprepare(): void
    {
        $clericClass = CharacterClass::factory()->preparedCaster('WIS')->create(['name' => 'Cleric']);
        $spell = Spell::factory()->create(['level' => 1]);
        $clericClass->spells()->attach($spell->id);

        $character = Character::factory()
            ->withClass($clericClass)
            ->withAbilityScores(['wisdom' => 14])
            ->level(1)
            ->create();

        // Create a spell with source 'prepared_from_list'
        CharacterSpell::create([
            'character_id' => $character->id,
            'spell_slug' => $spell->slug,
            'preparation_status' => 'prepared',
            'source' => 'prepared_from_list',
        ]);

        // Unprepare the spell
        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/spells/{$spell->slug}/unprepare"
        );

        $response->assertOk();

        // Row should be completely deleted, not just set to 'known'
        $this->assertDatabaseMissing('character_spells', [
            'character_id' => $character->id,
            'spell_slug' => $spell->slug,
        ]);
    }

    #[Test]
    public function it_keeps_class_source_spell_on_unprepare(): void
    {
        $wizardClass = CharacterClass::factory()->spellbookCaster('INT')->create(['name' => 'Wizard']);
        $spell = Spell::factory()->create(['level' => 1]);
        $wizardClass->spells()->attach($spell->id);

        $character = Character::factory()
            ->withClass($wizardClass)
            ->withAbilityScores(['intelligence' => 14])
            ->level(1)
            ->create();

        // Create a spellbook spell with source 'class'
        CharacterSpell::create([
            'character_id' => $character->id,
            'spell_slug' => $spell->slug,
            'preparation_status' => 'prepared',
            'source' => 'class',
        ]);

        // Unprepare the spell
        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/spells/{$spell->slug}/unprepare"
        );

        $response->assertOk()
            ->assertJsonPath('data.preparation_status', 'known');

        // Row should still exist with 'known' status
        $this->assertDatabaseHas('character_spells', [
            'character_id' => $character->id,
            'spell_slug' => $spell->slug,
            'preparation_status' => 'known',
            'source' => 'class',
        ]);
    }

    #[Test]
    public function it_rejects_unprepare_cantrip(): void
    {
        $clericClass = CharacterClass::factory()->preparedCaster('WIS')->create(['name' => 'Cleric']);
        $cantrip = Spell::factory()->cantrip()->create();

        $character = Character::factory()
            ->withClass($clericClass)
            ->withAbilityScores(['wisdom' => 14])
            ->level(1)
            ->create();

        // Cantrips are added via character creation with source 'class'
        CharacterSpell::create([
            'character_id' => $character->id,
            'spell_slug' => $cantrip->slug,
            'preparation_status' => 'known',
            'source' => 'class',
        ]);

        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/spells/{$cantrip->slug}/unprepare"
        );

        $response->assertUnprocessable();

        // Cantrip should still be there
        $this->assertDatabaseHas('character_spells', [
            'character_id' => $character->id,
            'spell_slug' => $cantrip->slug,
        ]);
    }
}
