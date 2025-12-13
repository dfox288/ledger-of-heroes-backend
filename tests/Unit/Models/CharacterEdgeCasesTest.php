<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\CharacterClassPivot;
use App\Models\CharacterEquipment;
use App\Models\CharacterSpellSlot;
use App\Models\Item;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Edge case tests for Character model.
 *
 * Tests null/empty handling, boundary conditions, and relationship edge cases.
 *
 * @see https://github.com/dfox288/ledger-of-heroes/issues/581
 */
#[Group('unit-db')]
class CharacterEdgeCasesTest extends TestCase
{
    use RefreshDatabase;

    // =========================================================================
    // Null/Empty Handling
    // =========================================================================

    #[Test]
    public function character_with_no_classes_has_zero_total_level(): void
    {
        $character = Character::factory()->create();

        // Ensure no classes
        CharacterClassPivot::where('character_id', $character->id)->delete();

        $this->assertEquals(0, $character->fresh()->total_level);
    }

    #[Test]
    public function character_with_no_classes_has_null_primary_class(): void
    {
        $character = Character::factory()->create();

        // Ensure no classes
        CharacterClassPivot::where('character_id', $character->id)->delete();

        $this->assertNull($character->fresh()->primary_class);
    }

    #[Test]
    public function character_with_no_classes_is_not_multiclass(): void
    {
        $character = Character::factory()->create();

        // Ensure no classes
        CharacterClassPivot::where('character_id', $character->id)->delete();

        $this->assertFalse($character->fresh()->is_multiclass);
    }

    #[Test]
    public function character_with_empty_equipment_returns_empty_collection(): void
    {
        $character = Character::factory()->create();

        // Ensure no equipment
        CharacterEquipment::where('character_id', $character->id)->delete();

        $this->assertCount(0, $character->fresh()->equipment);
        $this->assertTrue($character->fresh()->equipment->isEmpty());
    }

    #[Test]
    public function character_with_empty_spell_slots_returns_empty_collection(): void
    {
        $character = Character::factory()->create();

        // Ensure no spell slots
        CharacterSpellSlot::where('character_id', $character->id)->delete();

        $this->assertCount(0, $character->fresh()->spellSlots);
    }

    #[Test]
    public function character_with_null_portrait_url_returns_null(): void
    {
        $character = Character::factory()->create(['portrait_url' => null]);

        $this->assertNull($character->portrait_url);
    }

    #[Test]
    public function character_with_null_notes_returns_null(): void
    {
        $character = Character::factory()->create();

        // Character notes are in separate table, test relationship
        $this->assertCount(0, $character->notes);
    }

    // =========================================================================
    // Boundary Conditions - Level 20
    // =========================================================================

    #[Test]
    public function single_class_level_20_character(): void
    {
        $character = Character::factory()->create();
        $fighter = CharacterClass::factory()->create(['parent_class_id' => null]);

        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $fighter->slug,
            'level' => 20,
            'is_primary' => true,
            'order' => 1,
        ]);

        $character = $character->fresh();

        $this->assertEquals(20, $character->total_level);
        $this->assertFalse($character->is_multiclass);
    }

    #[Test]
    public function multiclass_totaling_level_20(): void
    {
        $character = Character::factory()->create();
        $fighter = CharacterClass::factory()->create(['name' => 'Fighter', 'parent_class_id' => null]);
        $wizard = CharacterClass::factory()->create(['name' => 'Wizard', 'parent_class_id' => null]);
        $cleric = CharacterClass::factory()->create(['name' => 'Cleric', 'parent_class_id' => null]);

        // 10 Fighter / 7 Wizard / 3 Cleric = 20 total
        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $fighter->slug,
            'level' => 10,
            'is_primary' => true,
            'order' => 1,
        ]);
        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $wizard->slug,
            'level' => 7,
            'is_primary' => false,
            'order' => 2,
        ]);
        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $cleric->slug,
            'level' => 3,
            'is_primary' => false,
            'order' => 3,
        ]);

        $character = $character->fresh();

        $this->assertEquals(20, $character->total_level);
        $this->assertTrue($character->is_multiclass);
        $this->assertCount(3, $character->characterClasses);
    }

    #[Test]
    public function level_1_character_has_correct_total_level(): void
    {
        $character = Character::factory()->create();
        $fighter = CharacterClass::factory()->create(['parent_class_id' => null]);

        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $fighter->slug,
            'level' => 1,
            'is_primary' => true,
            'order' => 1,
        ]);

        $this->assertEquals(1, $character->fresh()->total_level);
    }

    #[Test]
    public function character_classes_relationship_loads_class_model(): void
    {
        $character = Character::factory()->create();
        $fighter = CharacterClass::factory()->create(['name' => 'Fighter', 'parent_class_id' => null]);

        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $fighter->slug,
            'level' => 5,
            'is_primary' => true,
            'order' => 1,
        ]);

        $pivot = $character->fresh()->characterClasses->first();

        $this->assertNotNull($pivot->characterClass);
        $this->assertEquals('Fighter', $pivot->characterClass->name);
    }

    // =========================================================================
    // HP Boundary Conditions
    // =========================================================================

    #[Test]
    public function character_with_zero_current_hp(): void
    {
        $character = Character::factory()->create([
            'current_hit_points' => 0,
            'max_hit_points' => 45,
        ]);

        $this->assertEquals(0, $character->current_hit_points);
        $this->assertEquals(45, $character->max_hit_points);
    }

    #[Test]
    public function character_with_temp_hp_at_zero(): void
    {
        $character = Character::factory()->create([
            'current_hit_points' => 20,
            'max_hit_points' => 45,
            'temp_hit_points' => 0,
        ]);

        $this->assertEquals(0, $character->temp_hit_points);
    }

    #[Test]
    public function character_with_negative_hp_tracking(): void
    {
        // D&D 5e tracks negative HP for massive damage / instant death
        $character = Character::factory()->create([
            'current_hit_points' => -10,
            'max_hit_points' => 45,
        ]);

        $this->assertEquals(-10, $character->current_hit_points);
    }

    // =========================================================================
    // Death Save Edge Cases
    // =========================================================================

    #[Test]
    public function death_saves_at_zero(): void
    {
        $character = Character::factory()->create([
            'death_save_successes' => 0,
            'death_save_failures' => 0,
        ]);

        $this->assertEquals(0, $character->death_save_successes);
        $this->assertEquals(0, $character->death_save_failures);
    }

    #[Test]
    public function death_saves_at_maximum(): void
    {
        $character = Character::factory()->create([
            'death_save_successes' => 3,
            'death_save_failures' => 3,
        ]);

        $this->assertEquals(3, $character->death_save_successes);
        $this->assertEquals(3, $character->death_save_failures);
    }

    // =========================================================================
    // Equipment Edge Cases
    // =========================================================================

    #[Test]
    public function character_can_have_equipment_with_null_item_reference(): void
    {
        // Custom/freetext items have no item_id
        $character = Character::factory()->create();

        $equipment = CharacterEquipment::create([
            'character_id' => $character->id,
            'item_slug' => null,
            'custom_name' => 'Mystery Artifact',
            'quantity' => 1,
            'equipped' => false,
            'location' => 'backpack',
        ]);

        $this->assertNull($equipment->item);
        $this->assertEquals('Mystery Artifact', $equipment->custom_name);
        $this->assertTrue($equipment->isCustomItem());
    }

    #[Test]
    public function character_with_dangling_item_reference(): void
    {
        // Item slug that doesn't exist in items table
        $character = Character::factory()->create();

        $equipment = CharacterEquipment::create([
            'character_id' => $character->id,
            'item_slug' => 'nonexistent:item-slug',
            'quantity' => 1,
            'equipped' => false,
            'location' => 'backpack',
        ]);

        $this->assertNull($equipment->item);
        $this->assertEquals('nonexistent:item-slug', $equipment->item_slug);
    }
}
