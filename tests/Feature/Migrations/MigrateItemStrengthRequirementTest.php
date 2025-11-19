<?php

namespace Tests\Feature\Migrations;

use App\Models\AbilityScore;
use App\Models\EntityPrerequisite;
use App\Models\Item;
use App\Models\ItemType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MigrateItemStrengthRequirementTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_migrates_items_with_strength_requirement_to_prerequisites()
    {
        // Arrange: Create test items with strength requirements
        $itemType = ItemType::where('code', 'HA')->first();
        if (! $itemType) {
            $itemType = ItemType::create(['code' => 'HA', 'name' => 'Heavy Armor']);
        }

        $plateArmor = Item::factory()->create([
            'name' => 'Plate Armor',
            'slug' => 'plate-armor',
            'item_type_id' => $itemType->id,
            'strength_requirement' => 15,
        ]);

        $splintArmor = Item::factory()->create([
            'name' => 'Splint Armor',
            'slug' => 'splint-armor',
            'item_type_id' => $itemType->id,
            'strength_requirement' => 13,
        ]);

        $noRequirement = Item::factory()->create([
            'name' => 'Leather Armor',
            'slug' => 'leather-armor',
            'item_type_id' => $itemType->id,
            'strength_requirement' => null,
        ]);

        // Get STR ability score ID
        $strAbilityScore = AbilityScore::where('code', 'STR')->first();
        $this->assertNotNull($strAbilityScore, 'STR ability score must exist');

        // Act: Run the migration (we'll simulate it here)
        // In the actual migration, this will be done via artisan migrate
        $this->migrateItemStrengthRequirements();

        // Assert: Check that prerequisites were created
        $this->assertDatabaseHas('entity_prerequisites', [
            'reference_type' => Item::class,
            'reference_id' => $plateArmor->id,
            'prerequisite_type' => AbilityScore::class,
            'prerequisite_id' => $strAbilityScore->id,
            'minimum_value' => 15,
            'group_id' => 1,
        ]);

        $this->assertDatabaseHas('entity_prerequisites', [
            'reference_type' => Item::class,
            'reference_id' => $splintArmor->id,
            'prerequisite_type' => AbilityScore::class,
            'prerequisite_id' => $strAbilityScore->id,
            'minimum_value' => 13,
            'group_id' => 1,
        ]);

        // Assert: Item with no strength requirement has no prerequisites
        $this->assertEquals(0, EntityPrerequisite::where('reference_type', Item::class)
            ->where('reference_id', $noRequirement->id)
            ->count());

        // Assert: Legacy column is preserved
        $plateArmor->refresh();
        $this->assertEquals(15, $plateArmor->strength_requirement);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_is_idempotent_and_safe_to_run_multiple_times()
    {
        // Arrange: Create item with strength requirement
        $itemType = ItemType::where('code', 'HA')->first();
        if (! $itemType) {
            $itemType = ItemType::create(['code' => 'HA', 'name' => 'Heavy Armor']);
        }

        $item = Item::factory()->create([
            'name' => 'Chain Mail',
            'slug' => 'chain-mail',
            'item_type_id' => $itemType->id,
            'strength_requirement' => 13,
        ]);

        $strAbilityScore = AbilityScore::where('code', 'STR')->first();
        $this->assertNotNull($strAbilityScore);

        // Act: Run migration twice
        $this->migrateItemStrengthRequirements();
        $this->migrateItemStrengthRequirements();

        // Assert: Only one prerequisite record exists (no duplicates)
        $prerequisites = EntityPrerequisite::where('reference_type', Item::class)
            ->where('reference_id', $item->id)
            ->get();

        $this->assertCount(1, $prerequisites);
        $this->assertEquals($strAbilityScore->id, $prerequisites->first()->prerequisite_id);
        $this->assertEquals(13, $prerequisites->first()->minimum_value);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_items_without_strength_requirement()
    {
        // Arrange: Create items without strength requirements
        $itemType = ItemType::where('code', 'LA')->first();
        if (! $itemType) {
            $itemType = ItemType::create(['code' => 'LA', 'name' => 'Light Armor']);
        }

        $item1 = Item::factory()->create([
            'name' => 'Leather Armor',
            'slug' => 'leather-armor',
            'item_type_id' => $itemType->id,
            'strength_requirement' => null,
        ]);

        $item2 = Item::factory()->create([
            'name' => 'Studded Leather',
            'slug' => 'studded-leather',
            'item_type_id' => $itemType->id,
            'strength_requirement' => 0, // Explicitly 0
        ]);

        // Act: Run migration
        $this->migrateItemStrengthRequirements();

        // Assert: No prerequisites created for these items
        $this->assertEquals(0, EntityPrerequisite::where('reference_type', Item::class)
            ->where('reference_id', $item1->id)
            ->count());

        $this->assertEquals(0, EntityPrerequisite::where('reference_type', Item::class)
            ->where('reference_id', $item2->id)
            ->count());
    }

    /**
     * Simulate the migration logic (extracted for testing).
     * This is the same logic that will be in the actual migration.
     */
    private function migrateItemStrengthRequirements(): void
    {
        $strAbilityScore = AbilityScore::where('code', 'STR')->firstOrFail();

        // Get all items with strength requirements
        $items = Item::whereNotNull('strength_requirement')
            ->where('strength_requirement', '>', 0)
            ->get();

        foreach ($items as $item) {
            // Check if prerequisite already exists (idempotency)
            $exists = EntityPrerequisite::where('reference_type', Item::class)
                ->where('reference_id', $item->id)
                ->where('prerequisite_type', AbilityScore::class)
                ->where('prerequisite_id', $strAbilityScore->id)
                ->exists();

            if (! $exists) {
                EntityPrerequisite::create([
                    'reference_type' => Item::class,
                    'reference_id' => $item->id,
                    'prerequisite_type' => AbilityScore::class,
                    'prerequisite_id' => $strAbilityScore->id,
                    'minimum_value' => $item->strength_requirement,
                    'description' => null,
                    'group_id' => 1,
                ]);
            }
        }
    }
}
