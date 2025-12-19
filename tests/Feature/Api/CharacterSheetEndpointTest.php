<?php

namespace Tests\Feature\Api;

use App\Models\Character;
use App\Models\CharacterClassPivot;
use App\Models\CharacterEquipment;
use App\Models\CharacterLanguage;
use App\Models\CharacterNote;
use App\Models\CharacterProficiency;
use App\Models\CharacterSpell;
use App\Models\Item;
use App\Models\Language;
use App\Models\ProficiencyType;
use App\Models\Race;
use App\Models\Spell;
use App\Models\SpellSchool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests for the unified character sheet endpoint.
 *
 * GET /api/v1/characters/{id}/sheet
 *
 * Returns complete character sheet data in a single response.
 */
class CharacterSheetEndpointTest extends TestCase
{
    use RefreshDatabase;

    // =====================
    // Structure Tests
    // =====================

    #[Test]
    public function it_returns_sheet_data_structure(): void
    {
        $race = Race::factory()->create();
        $character = Character::factory()
            ->withStandardArray()
            ->create(['race_slug' => $race->slug]);

        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'level' => 5,
            'is_primary' => true,
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/sheet");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'character',
                    'stats',
                    'spells',
                    'equipment',
                    'features',
                    'notes',
                    'proficiencies',
                    'languages',
                ],
            ]);
    }

    #[Test]
    public function it_includes_full_character_resource(): void
    {
        $race = Race::factory()->create();
        $character = Character::factory()
            ->withStandardArray()
            ->create([
                'name' => 'Gandalf the Grey',
                'race_slug' => $race->slug,
            ]);

        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'level' => 10,
            'is_primary' => true,
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/sheet");

        $response->assertOk()
            ->assertJsonPath('data.character.name', 'Gandalf the Grey')
            ->assertJsonPath('data.character.total_level', 10);
    }

    #[Test]
    public function it_includes_stats_resource(): void
    {
        $character = Character::factory()
            ->withStandardArray()
            ->create([
                'max_hit_points' => 45,
                'current_hit_points' => 35,
            ]);

        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'level' => 5,
            'is_primary' => true,
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/sheet");

        $response->assertOk();

        $stats = $response->json('data.stats');
        $this->assertArrayHasKey('armor_class', $stats);
        $this->assertArrayHasKey('hit_points', $stats);
        $this->assertArrayHasKey('saving_throws', $stats);
        $this->assertArrayHasKey('proficiency_bonus', $stats);
    }

    // =====================
    // Spells Tests
    // =====================

    #[Test]
    public function it_includes_all_spells_not_just_prepared(): void
    {
        $character = Character::factory()->withStandardArray()->create();
        $school = SpellSchool::factory()->create();

        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'level' => 5,
            'is_primary' => true,
        ]);

        $fireball = Spell::factory()->create([
            'name' => 'Fireball',
            'slug' => 'test:fireball-'.uniqid(),
            'spell_school_id' => $school->id,
        ]);

        $shield = Spell::factory()->create([
            'name' => 'Shield',
            'slug' => 'test:shield-'.uniqid(),
            'spell_school_id' => $school->id,
        ]);

        $magicMissile = Spell::factory()->create([
            'name' => 'Magic Missile',
            'slug' => 'test:magic-missile-'.uniqid(),
            'spell_school_id' => $school->id,
        ]);

        // Prepared spell
        CharacterSpell::create([
            'character_id' => $character->id,
            'spell_slug' => $fireball->slug,
            'preparation_status' => 'prepared',
            'source' => 'class',
        ]);

        // Always prepared spell
        CharacterSpell::create([
            'character_id' => $character->id,
            'spell_slug' => $shield->slug,
            'preparation_status' => 'always_prepared',
            'source' => 'class',
        ]);

        // Known but not prepared
        CharacterSpell::create([
            'character_id' => $character->id,
            'spell_slug' => $magicMissile->slug,
            'preparation_status' => 'known',
            'source' => 'class',
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/sheet");

        $response->assertOk();

        $spells = $response->json('data.spells');
        $this->assertCount(3, $spells);
    }

    // =====================
    // Equipment Tests
    // =====================

    #[Test]
    public function it_includes_all_equipment(): void
    {
        $character = Character::factory()->withStandardArray()->create();

        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'level' => 1,
            'is_primary' => true,
        ]);

        $sword = Item::factory()->create([
            'name' => 'Longsword',
            'slug' => 'test:longsword-'.uniqid(),
        ]);

        $armor = Item::factory()->create([
            'name' => 'Chain Mail',
            'slug' => 'test:chain-mail-'.uniqid(),
        ]);

        $potion = Item::factory()->create([
            'name' => 'Healing Potion',
            'slug' => 'test:healing-potion-'.uniqid(),
        ]);

        CharacterEquipment::create([
            'character_id' => $character->id,
            'item_slug' => $sword->slug,
            'quantity' => 1,
            'equipped' => true,
            'location' => 'main_hand',
        ]);

        CharacterEquipment::create([
            'character_id' => $character->id,
            'item_slug' => $armor->slug,
            'quantity' => 1,
            'equipped' => true,
            'location' => 'armor',
        ]);

        CharacterEquipment::create([
            'character_id' => $character->id,
            'item_slug' => $potion->slug,
            'quantity' => 3,
            'equipped' => false,
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/sheet");

        $response->assertOk();

        $equipment = $response->json('data.equipment');
        $this->assertCount(3, $equipment);
    }

    // =====================
    // Languages & Proficiencies Tests
    // =====================

    #[Test]
    public function it_includes_languages(): void
    {
        $character = Character::factory()->withStandardArray()->create();

        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'level' => 1,
            'is_primary' => true,
        ]);

        // Use unique names and slugs to avoid conflicts
        $uniqueId = uniqid();
        $lang1 = Language::factory()->create([
            'name' => 'TestLang1-'.$uniqueId,
            'slug' => 'test:lang1-'.$uniqueId,
        ]);

        $lang2 = Language::factory()->create([
            'name' => 'TestLang2-'.$uniqueId,
            'slug' => 'test:lang2-'.$uniqueId,
        ]);

        CharacterLanguage::create([
            'character_id' => $character->id,
            'language_slug' => $lang1->slug,
            'source' => 'race',
        ]);

        CharacterLanguage::create([
            'character_id' => $character->id,
            'language_slug' => $lang2->slug,
            'source' => 'race',
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/sheet");

        $response->assertOk();

        $languages = $response->json('data.languages');
        $this->assertCount(2, $languages);
    }

    #[Test]
    public function it_includes_proficiencies(): void
    {
        $character = Character::factory()->withStandardArray()->create();

        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'level' => 1,
            'is_primary' => true,
        ]);

        $profType = ProficiencyType::factory()->create();

        CharacterProficiency::create([
            'character_id' => $character->id,
            'proficiency_type_id' => $profType->id,
            'target_type' => 'skill',
            'target_slug' => 'phb:athletics',
            'source' => 'class',
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/sheet");

        $response->assertOk();

        $proficiencies = $response->json('data.proficiencies');
        $this->assertCount(1, $proficiencies);
    }

    // =====================
    // Notes Tests
    // =====================

    #[Test]
    public function it_includes_notes_grouped(): void
    {
        $character = Character::factory()->withStandardArray()->create();

        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'level' => 1,
            'is_primary' => true,
        ]);

        CharacterNote::create([
            'character_id' => $character->id,
            'category' => 'personality_traits',
            'content' => 'Always tells the truth',
        ]);

        CharacterNote::create([
            'character_id' => $character->id,
            'category' => 'ideals',
            'content' => 'Justice above all',
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/sheet");

        $response->assertOk();

        $notes = $response->json('data.notes');
        $this->assertIsArray($notes);
    }

    // =====================
    // Access Tests
    // =====================

    #[Test]
    public function it_returns_404_for_nonexistent_character(): void
    {
        $response = $this->getJson('/api/v1/characters/99999/sheet');

        $response->assertNotFound();
    }

    #[Test]
    public function it_works_with_public_id(): void
    {
        $character = Character::factory()->withStandardArray()->create();

        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'level' => 1,
            'is_primary' => true,
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->public_id}/sheet");

        $response->assertOk()
            ->assertJsonPath('data.character.id', $character->id);
    }
}
