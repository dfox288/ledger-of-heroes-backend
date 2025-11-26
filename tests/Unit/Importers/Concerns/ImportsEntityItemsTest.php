<?php

namespace Tests\Unit\Importers\Concerns;

use App\Models\Item;
use App\Services\Importers\Concerns\ImportsEntityItems;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('unit-db')]
class ImportsEntityItemsTest extends TestCase
{
    use RefreshDatabase;

    private object $traitUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Create anonymous class using the trait
        $this->traitUser = new class
        {
            use ImportsEntityItems;

            public function match(string $description): ?Item
            {
                return $this->matchItemByDescription($description);
            }
        };

        // Seed common items for testing
        Item::factory()->create(['name' => 'Rapier']);
        Item::factory()->create(['name' => 'Shortsword']);
        Item::factory()->create(['name' => 'Shortbow']);
        Item::factory()->create(['name' => 'Leather Armor']);
        Item::factory()->create(['name' => 'Dagger']);
        Item::factory()->create(['name' => 'Javelin']);
        Item::factory()->create(['name' => "Burglar's Pack"]);
        Item::factory()->create(['name' => "Dungeoneer's Pack"]);
        Item::factory()->create(['name' => "Explorer's Pack"]);
        Item::factory()->create(['name' => "Thieves' Tools"]);
        Item::factory()->create(['name' => 'Arrows']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_matches_exact_item_names()
    {
        $item = $this->traitUser->match('Rapier');

        $this->assertNotNull($item);
        $this->assertEquals('Rapier', $item->name);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_removes_leading_articles()
    {
        $cases = [
            'a rapier' => 'Rapier',
            'an explorer' => "Explorer's Pack",  // Fuzzy match
            'the shortsword' => 'Shortsword',
        ];

        foreach ($cases as $description => $expectedName) {
            $item = $this->traitUser->match($description);
            $this->assertNotNull($item, "Failed to match: {$description}");
            $this->assertEquals($expectedName, $item->name);
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_compound_items_with_and()
    {
        $item = $this->traitUser->match('a shortbow and quiver of arrows (20)');

        $this->assertNotNull($item);
        $this->assertEquals('Shortbow', $item->name);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_removes_quantity_words()
    {
        $cases = [
            'two dagger' => 'Dagger',
            'four javelins' => 'Javelin',  // Also tests plural handling
            'twenty arrows' => 'Arrows',
        ];

        foreach ($cases as $description => $expectedName) {
            $item = $this->traitUser->match($description);
            $this->assertNotNull($item, "Failed to match: {$description}");
            $this->assertEquals($expectedName, $item->name);
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_plurals()
    {
        // "javelins" should match "Javelin"
        $item = $this->traitUser->match('javelins');

        $this->assertNotNull($item);
        $this->assertEquals('Javelin', $item->name);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_case_insensitivity()
    {
        $cases = [
            'RAPIER' => 'Rapier',
            'leather armor' => 'Leather Armor',
            'ThIeVeS\' ToOlS' => "Thieves' Tools",
        ];

        foreach ($cases as $description => $expectedName) {
            $item = $this->traitUser->match($description);
            $this->assertNotNull($item, "Failed to match: {$description}");
            $this->assertEquals($expectedName, $item->name);
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_possessives()
    {
        $item = $this->traitUser->match("a burglar's pack");

        $this->assertNotNull($item);
        $this->assertEquals("Burglar's Pack", $item->name);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_null_for_unmatched_items()
    {
        $item = $this->traitUser->match('any martial weapon');

        $this->assertNull($item);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_complex_real_world_cases()
    {
        $cases = [
            'a rapier' => 'Rapier',
            'a shortsword' => 'Shortsword',
            'a shortbow and quiver of arrows (20)' => 'Shortbow',
            "a burglar's pack" => "Burglar's Pack",
            "a dungeoneer's pack" => "Dungeoneer's Pack",
            "an explorer's pack" => "Explorer's Pack",
            'Leather armor' => 'Leather Armor',
            'two dagger' => 'Dagger',
            "thieves' tools" => "Thieves' Tools",
        ];

        foreach ($cases as $description => $expectedName) {
            $item = $this->traitUser->match($description);
            $this->assertNotNull($item, "Failed to match: {$description}");
            $this->assertEquals($expectedName, $item->name, "Matched wrong item for: {$description}");
        }
    }
}
