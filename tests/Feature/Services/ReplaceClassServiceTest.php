<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Exceptions\ClassReplacementException;
use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\CharacterClassPivot;
use App\Services\ReplaceClassService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('feature-db')]
class ReplaceClassServiceTest extends TestCase
{
    use RefreshDatabase;

    private ReplaceClassService $service;

    private CharacterClass $fighter;

    private CharacterClass $wizard;

    private CharacterClass $rogue;

    private CharacterClass $battleMaster; // subclass

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(ReplaceClassService::class);

        $this->fighter = CharacterClass::factory()->create([
            'slug' => 'test:fighter',
            'name' => 'Fighter',
            'hit_die' => 10,
            'parent_class_id' => null,
        ]);

        $this->wizard = CharacterClass::factory()->create([
            'slug' => 'test:wizard',
            'name' => 'Wizard',
            'hit_die' => 6,
            'parent_class_id' => null,
        ]);

        $this->rogue = CharacterClass::factory()->create([
            'slug' => 'test:rogue',
            'name' => 'Rogue',
            'hit_die' => 8,
            'parent_class_id' => null,
        ]);

        $this->battleMaster = CharacterClass::factory()->create([
            'slug' => 'test:battle-master',
            'name' => 'Battle Master',
            'hit_die' => 10,
            'parent_class_id' => $this->fighter->id,
        ]);
    }

    // =========================================================================
    // Happy Path Tests
    // =========================================================================

    #[Test]
    public function it_replaces_class_for_level_1_single_class_character(): void
    {
        $character = Character::factory()->create();
        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $this->fighter->slug,
            'level' => 1,
            'is_primary' => true,
            'order' => 1,
        ]);

        $newPivot = $this->service->replaceClass($character, $this->fighter, $this->wizard);

        $this->assertEquals($this->wizard->slug, $newPivot->class_slug);
        $this->assertEquals(1, $newPivot->level);
        $this->assertTrue($newPivot->is_primary);
        $this->assertEquals(1, $newPivot->order);
        $this->assertNull($newPivot->subclass_slug);
        $this->assertEquals(0, $newPivot->hit_dice_spent);

        // Verify database persistence
        $this->assertDatabaseHas('character_classes', [
            'character_id' => $character->id,
            'class_slug' => $this->wizard->slug,
            'level' => 1,
        ]);
    }

    #[Test]
    public function it_preserves_is_primary_flag_when_replacing(): void
    {
        $character = Character::factory()->create();
        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $this->fighter->slug,
            'level' => 1,
            'is_primary' => true,
            'order' => 1,
        ]);

        $newPivot = $this->service->replaceClass($character, $this->fighter, $this->wizard);

        $this->assertTrue($newPivot->is_primary);
    }

    #[Test]
    public function it_preserves_order_when_replacing(): void
    {
        $character = Character::factory()->create();
        // Use order=5 (non-sequential) to verify order is preserved, not reset to 1
        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $this->fighter->slug,
            'level' => 1,
            'is_primary' => true,
            'order' => 5,
        ]);

        $newPivot = $this->service->replaceClass($character, $this->fighter, $this->wizard);

        $this->assertEquals(5, $newPivot->order);
    }

    #[Test]
    public function it_clears_subclass_when_replacing(): void
    {
        $character = Character::factory()->create();
        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $this->fighter->slug,
            'subclass_slug' => $this->battleMaster->slug,
            'level' => 1,
            'is_primary' => true,
            'order' => 1,
        ]);

        $newPivot = $this->service->replaceClass($character, $this->fighter, $this->wizard);

        $this->assertNull($newPivot->subclass_slug);
    }

    #[Test]
    public function it_resets_hit_dice_spent_when_replacing(): void
    {
        $character = Character::factory()->create();
        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $this->fighter->slug,
            'level' => 1,
            'is_primary' => true,
            'order' => 1,
            'hit_dice_spent' => 1,
        ]);

        $newPivot = $this->service->replaceClass($character, $this->fighter, $this->wizard);

        $this->assertEquals(0, $newPivot->hit_dice_spent);
    }

    #[Test]
    public function it_removes_old_class_pivot_after_replacement(): void
    {
        $character = Character::factory()->create();
        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $this->fighter->slug,
            'level' => 1,
            'is_primary' => true,
            'order' => 1,
        ]);

        $this->service->replaceClass($character, $this->fighter, $this->wizard);

        $character->refresh();
        $this->assertCount(1, $character->characterClasses);
        $this->assertEquals($this->wizard->slug, $character->characterClasses->first()->class_slug);
        $this->assertNull($character->characterClasses->where('class_slug', $this->fighter->slug)->first());

        // Verify old pivot is actually deleted from database
        $this->assertDatabaseMissing('character_classes', [
            'character_id' => $character->id,
            'class_slug' => $this->fighter->slug,
        ]);
    }

    // =========================================================================
    // Validation Error Tests
    // =========================================================================

    #[Test]
    public function it_throws_exception_when_source_class_not_found_on_character(): void
    {
        $character = Character::factory()->create();
        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $this->fighter->slug,
            'level' => 1,
            'is_primary' => true,
            'order' => 1,
        ]);

        $this->expectException(ClassReplacementException::class);
        $this->expectExceptionMessage('Class not found on character');

        $this->service->replaceClass($character, $this->rogue, $this->wizard);
    }

    #[Test]
    public function it_throws_exception_when_character_level_is_greater_than_1(): void
    {
        $character = Character::factory()->create();
        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $this->fighter->slug,
            'level' => 2,
            'is_primary' => true,
            'order' => 1,
        ]);

        $this->expectException(ClassReplacementException::class);
        $this->expectExceptionMessage('Can only replace class at level 1');

        $this->service->replaceClass($character, $this->fighter, $this->wizard);
    }

    #[Test]
    public function it_throws_exception_when_character_has_multiple_classes(): void
    {
        $character = Character::factory()->create();
        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $this->fighter->slug,
            'level' => 1,
            'is_primary' => true,
            'order' => 1,
        ]);
        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $this->rogue->slug,
            'level' => 1,
            'is_primary' => false,
            'order' => 2,
        ]);

        $this->expectException(ClassReplacementException::class);
        $this->expectExceptionMessage('Cannot replace class when character has multiple classes');

        $this->service->replaceClass($character, $this->fighter, $this->wizard);
    }

    #[Test]
    public function it_throws_exception_when_replacing_with_same_class(): void
    {
        $character = Character::factory()->create();
        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $this->fighter->slug,
            'level' => 1,
            'is_primary' => true,
            'order' => 1,
        ]);

        $this->expectException(ClassReplacementException::class);
        $this->expectExceptionMessage('Cannot replace class with the same class');

        $this->service->replaceClass($character, $this->fighter, $this->fighter);
    }

    #[Test]
    public function it_throws_exception_when_target_is_a_subclass(): void
    {
        $character = Character::factory()->create();
        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $this->wizard->slug,
            'level' => 1,
            'is_primary' => true,
            'order' => 1,
        ]);

        $this->expectException(ClassReplacementException::class);
        $this->expectExceptionMessage('Cannot use a subclass as the replacement class');

        $this->service->replaceClass($character, $this->wizard, $this->battleMaster);
    }

    // =========================================================================
    // Integration Tests (verifying service interactions)
    // =========================================================================

    #[Test]
    public function it_clears_proficiencies_from_old_class(): void
    {
        $character = Character::factory()->create();
        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $this->fighter->slug,
            'level' => 1,
            'is_primary' => true,
            'order' => 1,
        ]);

        // Add a class-sourced proficiency that should be cleared
        $character->proficiencies()->create([
            'proficiency_type_slug' => 'test:heavy-armor',
            'source' => 'class',
        ]);

        // Add a race-sourced proficiency that should be preserved
        $character->proficiencies()->create([
            'proficiency_type_slug' => 'test:darkvision',
            'source' => 'race',
        ]);

        $this->assertCount(2, $character->proficiencies);

        $this->service->replaceClass($character, $this->fighter, $this->wizard);

        $character->refresh();
        $this->assertCount(0, $character->proficiencies()->where('source', 'class')->get());
        $this->assertCount(1, $character->proficiencies()->where('source', 'race')->get());
    }

    #[Test]
    public function it_returns_the_new_character_class_pivot(): void
    {
        $character = Character::factory()->create();
        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $this->fighter->slug,
            'level' => 1,
            'is_primary' => true,
            'order' => 1,
        ]);

        $result = $this->service->replaceClass($character, $this->fighter, $this->wizard);

        $this->assertInstanceOf(CharacterClassPivot::class, $result);
        $this->assertEquals($character->id, $result->character_id);
        $this->assertEquals($this->wizard->slug, $result->class_slug);
    }
}
