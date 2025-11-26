<?php

namespace Tests\Feature\Api;

use App\Models\AbilityScore;
use App\Models\EntityPrerequisite;
use App\Models\Item;
use App\Models\ItemType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('feature-db')]
class ItemPrerequisitesApiTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_includes_prerequisites_in_item_show_response()
    {
        // Arrange: Create item with strength requirement prerequisite
        $itemType = ItemType::where('code', 'HA')->first();
        if (! $itemType) {
            $itemType = ItemType::create(['code' => 'HA', 'name' => 'Heavy Armor']);
        }

        $item = Item::factory()->create([
            'name' => 'Plate Armor',
            'slug' => 'plate-armor',
            'item_type_id' => $itemType->id,
            'strength_requirement' => 15,
        ]);

        $strAbilityScore = AbilityScore::where('code', 'STR')->first();

        EntityPrerequisite::create([
            'reference_type' => Item::class,
            'reference_id' => $item->id,
            'prerequisite_type' => AbilityScore::class,
            'prerequisite_id' => $strAbilityScore->id,
            'minimum_value' => 15,
            'description' => null,
            'group_id' => 1,
        ]);

        // Act: Request item via API
        $response = $this->getJson("/api/v1/items/{$item->slug}");

        // Assert: Response includes prerequisites
        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'slug',
                    'strength_requirement',
                    'prerequisites' => [
                        '*' => [
                            'id',
                            'prerequisite_type',
                            'prerequisite_id',
                            'minimum_value',
                            'group_id',
                            'prerequisite',
                        ],
                    ],
                ],
            ])
            ->assertJsonPath('data.name', 'Plate Armor')
            ->assertJsonPath('data.strength_requirement', 15)
            ->assertJsonCount(1, 'data.prerequisites')
            ->assertJsonPath('data.prerequisites.0.prerequisite_type', AbilityScore::class)
            ->assertJsonPath('data.prerequisites.0.prerequisite_id', $strAbilityScore->id)
            ->assertJsonPath('data.prerequisites.0.minimum_value', 15)
            ->assertJsonPath('data.prerequisites.0.group_id', 1);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_includes_prerequisites_in_item_index_response()
    {
        // Arrange: Create items with and without prerequisites
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

        $leatherArmor = Item::factory()->create([
            'name' => 'Leather Armor',
            'slug' => 'leather-armor',
            'item_type_id' => $itemType->id,
            'strength_requirement' => null,
        ]);

        $strAbilityScore = AbilityScore::where('code', 'STR')->first();

        EntityPrerequisite::create([
            'reference_type' => Item::class,
            'reference_id' => $plateArmor->id,
            'prerequisite_type' => AbilityScore::class,
            'prerequisite_id' => $strAbilityScore->id,
            'minimum_value' => 15,
            'description' => null,
            'group_id' => 1,
        ]);

        // Act: Request items index
        $response = $this->getJson('/api/v1/items');

        // Assert: Response includes prerequisites for items that have them
        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'slug',
                        'strength_requirement',
                        'prerequisites',
                    ],
                ],
            ]);

        // Check that plate armor has prerequisites
        $data = $response->json('data');
        $plateData = collect($data)->firstWhere('slug', 'plate-armor');
        $this->assertNotNull($plateData);
        $this->assertCount(1, $plateData['prerequisites']);
        $this->assertEquals(15, $plateData['prerequisites'][0]['minimum_value']);

        // Check that leather armor has no prerequisites
        $leatherData = collect($data)->firstWhere('slug', 'leather-armor');
        $this->assertNotNull($leatherData);
        $this->assertCount(0, $leatherData['prerequisites']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_empty_prerequisites_array_for_items_without_requirements()
    {
        // Arrange: Create item without prerequisites
        $itemType = ItemType::where('code', 'LA')->first();
        if (! $itemType) {
            $itemType = ItemType::create(['code' => 'LA', 'name' => 'Light Armor']);
        }

        $item = Item::factory()->create([
            'name' => 'Leather Armor',
            'slug' => 'leather-armor',
            'item_type_id' => $itemType->id,
            'strength_requirement' => null,
        ]);

        // Act: Request item via API
        $response = $this->getJson("/api/v1/items/{$item->slug}");

        // Assert: Response includes empty prerequisites array
        $response->assertOk()
            ->assertJsonPath('data.name', 'Leather Armor')
            ->assertJsonPath('data.strength_requirement', null)
            ->assertJsonCount(0, 'data.prerequisites');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_eager_loads_prerequisites_to_avoid_n_plus_1_queries()
    {
        // Arrange: Create multiple items with prerequisites
        $itemType = ItemType::where('code', 'HA')->first();
        if (! $itemType) {
            $itemType = ItemType::create(['code' => 'HA', 'name' => 'Heavy Armor']);
        }

        $strAbilityScore = AbilityScore::where('code', 'STR')->first();

        for ($i = 1; $i <= 3; $i++) {
            $item = Item::factory()->create([
                'name' => "Test Armor {$i}",
                'slug' => "test-armor-{$i}",
                'item_type_id' => $itemType->id,
                'strength_requirement' => 13 + $i,
            ]);

            EntityPrerequisite::create([
                'reference_type' => Item::class,
                'reference_id' => $item->id,
                'prerequisite_type' => AbilityScore::class,
                'prerequisite_id' => $strAbilityScore->id,
                'minimum_value' => 13 + $i,
                'description' => null,
                'group_id' => 1,
            ]);
        }

        // Act & Assert: Should load prerequisites efficiently
        $this->enableQueryLog();

        $response = $this->getJson('/api/v1/items');

        $response->assertOk();

        // Verify query count is reasonable (exact count depends on other relationships)
        // Main assertion: prerequisites should be eager-loaded, not queried per item
        $queries = $this->getQueryLog();

        // Should not see N queries for prerequisites (one per item)
        // Instead, should see 1-2 queries for all prerequisites at once
        $prerequisiteQueries = array_filter($queries, fn ($q) => str_contains($q['query'], 'entity_prerequisites'));

        // Should be 1 query for prerequisites (eager-loaded), not N queries
        $this->assertLessThanOrEqual(2, count($prerequisiteQueries), 'Prerequisites should be eager-loaded');
    }

    /**
     * Enable query logging for testing.
     */
    private function enableQueryLog(): void
    {
        \DB::enableQueryLog();
    }

    /**
     * Get the query log.
     */
    private function getQueryLog(): array
    {
        return \DB::getQueryLog();
    }
}
