<?php

namespace Tests\Feature\Api;

use App\Models\ProficiencyType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProficiencyTypeApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\ProficiencyTypeSeeder::class);
    }

    #[Test]
    public function it_can_get_all_proficiency_types(): void
    {
        $response = $this->getJson('/api/v1/proficiency-types');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'slug', 'name', 'category', 'subcategory'],
                ],
            ]);
    }

    #[Test]
    public function it_retrieves_proficiency_type_by_slug(): void
    {
        $type = ProficiencyType::where('name', "Alchemist's Supplies")->first();

        $response = $this->getJson('/api/v1/proficiency-types/alchemists-supplies');

        $response->assertOk()
            ->assertJsonFragment([
                'slug' => 'alchemists-supplies',
                'name' => "Alchemist's Supplies",
            ]);
    }

    #[Test]
    public function it_retrieves_proficiency_type_by_slug_with_apostrophe(): void
    {
        $type = ProficiencyType::where('name', "Cook's Utensils")->first();

        $response = $this->getJson('/api/v1/proficiency-types/cooks-utensils');

        $response->assertOk()
            ->assertJsonFragment([
                'slug' => 'cooks-utensils',
                'name' => "Cook's Utensils",
            ]);
    }

    #[Test]
    public function it_retrieves_proficiency_type_by_slug_with_spaces(): void
    {
        $response = $this->getJson('/api/v1/proficiency-types/light-armor');

        $response->assertOk()
            ->assertJsonFragment([
                'slug' => 'light-armor',
                'name' => 'Light Armor',
            ]);
    }

    #[Test]
    public function it_can_filter_by_category(): void
    {
        $response = $this->getJson('/api/v1/proficiency-types?category=weapon');

        $response->assertOk();

        $data = $response->json('data');
        $this->assertNotEmpty($data);
        foreach ($data as $item) {
            $this->assertEquals('weapon', $item['category']);
        }
    }

    #[Test]
    public function it_can_filter_by_subcategory(): void
    {
        $response = $this->getJson('/api/v1/proficiency-types?subcategory=artisan');

        $response->assertOk();

        $data = $response->json('data');
        $this->assertNotEmpty($data);
        foreach ($data as $item) {
            $this->assertEquals('artisan', $item['subcategory']);
        }
    }

    #[Test]
    public function it_can_filter_by_category_and_subcategory(): void
    {
        $response = $this->getJson('/api/v1/proficiency-types?category=tool&subcategory=artisan');

        $response->assertOk();

        $data = $response->json('data');
        $this->assertNotEmpty($data);
        foreach ($data as $item) {
            $this->assertEquals('tool', $item['category']);
            $this->assertEquals('artisan', $item['subcategory']);
        }
    }

    #[Test]
    public function it_can_get_misc_tools(): void
    {
        $response = $this->getJson('/api/v1/proficiency-types?category=tool&subcategory=misc');

        $response->assertOk();

        $data = $response->json('data');
        $this->assertNotEmpty($data);

        $names = collect($data)->pluck('name')->toArray();
        $this->assertContains("Thieves' Tools", $names);
        $this->assertContains('Disguise Kit', $names);
    }

    #[Test]
    public function it_includes_subcategory_in_resource(): void
    {
        $proficiencyType = ProficiencyType::where('name', "Alchemist's Supplies")->first();

        $response = $this->getJson("/api/v1/proficiency-types/{$proficiencyType->slug}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['id', 'slug', 'name', 'category', 'subcategory', 'item'],
            ])
            ->assertJsonPath('data.subcategory', 'artisan');
    }

    #[Test]
    public function it_can_search_proficiency_types_using_q_parameter(): void
    {
        $response = $this->getJson('/api/v1/proficiency-types?q=sword');

        $response->assertOk();

        $data = $response->json('data');
        $this->assertNotEmpty($data);

        foreach ($data as $item) {
            $this->assertStringContainsStringIgnoringCase('sword', $item['name']);
        }
    }

    #[Test]
    public function artisan_tools_have_artisan_subcategory(): void
    {
        $artisanTools = ProficiencyType::where('subcategory', 'artisan')->get();

        $this->assertCount(17, $artisanTools);

        $names = $artisanTools->pluck('name')->toArray();
        $this->assertContains("Alchemist's Supplies", $names);
        $this->assertContains("Smith's Tools", $names);
        $this->assertContains("Carpenter's Tools", $names);
    }

    #[Test]
    public function misc_tools_have_misc_subcategory(): void
    {
        $miscTools = ProficiencyType::where('subcategory', 'misc')->get();

        $this->assertCount(6, $miscTools);

        $names = $miscTools->pluck('name')->toArray();
        $this->assertContains("Thieves' Tools", $names);
        $this->assertContains('Disguise Kit', $names);
        $this->assertContains("Navigator's Tools", $names);
    }

    #[Test]
    public function gaming_sets_have_null_subcategory(): void
    {
        $gamingSets = ProficiencyType::where('category', 'gaming_set')->get();

        $this->assertGreaterThan(0, $gamingSets->count());

        foreach ($gamingSets as $gamingSet) {
            $this->assertNull($gamingSet->subcategory);
        }
    }

    #[Test]
    public function musical_instruments_have_null_subcategory(): void
    {
        $instruments = ProficiencyType::where('category', 'musical_instrument')->get();

        $this->assertGreaterThan(0, $instruments->count());

        foreach ($instruments as $instrument) {
            $this->assertNull($instrument->subcategory);
        }
    }
}
