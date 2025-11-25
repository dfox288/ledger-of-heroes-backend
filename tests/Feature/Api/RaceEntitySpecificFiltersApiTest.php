<?php

namespace Tests\Feature\Api;

use App\Models\AbilityScore;
use App\Models\CharacterTrait;
use App\Models\Modifier;
use App\Models\Race;
use App\Models\Size;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RaceEntitySpecificFiltersApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Flush Meilisearch races index before each test
        // This ensures a clean state for filter tests
        try {
            Race::removeAllFromSearch();
        } catch (\Exception $e) {
            // Index might not exist yet - that's OK
        }

        // Configure Meilisearch indexes to ensure filterable attributes are set
        $this->artisan('search:configure-indexes');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_races_by_ability_bonus_int(): void
    {
        // Arrange: Create races with INT bonuses
        $intAbility = AbilityScore::where('code', 'INT')->first();

        $highElf = Race::factory()->create(['name' => 'High Elf']);
        Modifier::factory()->create([
            'reference_type' => Race::class,
            'reference_id' => $highElf->id,
            'modifier_category' => 'ability_score',
            'ability_score_id' => $intAbility->id,
            'value' => 1,
        ]);
        $highElf->fresh()->searchable();

        $gnome = Race::factory()->create(['name' => 'Gnome']);
        Modifier::factory()->create([
            'reference_type' => Race::class,
            'reference_id' => $gnome->id,
            'modifier_category' => 'ability_score',
            'ability_score_id' => $intAbility->id,
            'value' => 2,
        ]);
        $gnome->fresh()->searchable();

        // Create race without INT bonus
        $strAbility = AbilityScore::where('code', 'STR')->first();
        $mountainDwarf = Race::factory()->create(['name' => 'Mountain Dwarf']);
        Modifier::factory()->create([
            'reference_type' => Race::class,
            'reference_id' => $mountainDwarf->id,
            'modifier_category' => 'ability_score',
            'ability_score_id' => $strAbility->id,
            'value' => 2,
        ]);
        $mountainDwarf->fresh()->searchable();

        sleep(1); // Wait for Meilisearch indexing

        // Act: Filter by ability_int_bonus > 0 using Meilisearch
        $response = $this->getJson('/api/v1/races?filter=ability_int_bonus > 0');

        // Assert: Only races with INT bonus returned
        $response->assertOk();
        $data = $response->json('data');

        $this->assertCount(2, $data);
        $names = collect($data)->pluck('name')->toArray();
        $this->assertContains('High Elf', $names);
        $this->assertContains('Gnome', $names);
        $this->assertNotContains('Mountain Dwarf', $names);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_races_by_ability_bonus_str(): void
    {
        // Arrange: Create races with STR bonuses
        $strAbility = AbilityScore::where('code', 'STR')->first();

        $mountainDwarf = Race::factory()->create(['name' => 'Mountain Dwarf']);
        Modifier::factory()->create([
            'reference_type' => Race::class,
            'reference_id' => $mountainDwarf->id,
            'modifier_category' => 'ability_score',
            'ability_score_id' => $strAbility->id,
            'value' => 2,
        ]);
        $mountainDwarf->fresh()->searchable();

        $dragonborn = Race::factory()->create(['name' => 'Dragonborn']);
        Modifier::factory()->create([
            'reference_type' => Race::class,
            'reference_id' => $dragonborn->id,
            'modifier_category' => 'ability_score',
            'ability_score_id' => $strAbility->id,
            'value' => 2,
        ]);
        $dragonborn->fresh()->searchable();

        // Create race without STR bonus
        $intAbility = AbilityScore::where('code', 'INT')->first();
        $gnome = Race::factory()->create(['name' => 'Gnome']);
        Modifier::factory()->create([
            'reference_type' => Race::class,
            'reference_id' => $gnome->id,
            'modifier_category' => 'ability_score',
            'ability_score_id' => $intAbility->id,
            'value' => 2,
        ]);
        $gnome->fresh()->searchable();

        sleep(1); // Wait for Meilisearch indexing

        // Act: Filter by ability_str_bonus > 0 using Meilisearch
        $response = $this->getJson('/api/v1/races?filter=ability_str_bonus > 0');

        // Assert: Only races with STR bonus returned
        $response->assertOk();
        $data = $response->json('data');

        $this->assertCount(2, $data);
        $names = collect($data)->pluck('name')->toArray();
        $this->assertContains('Mountain Dwarf', $names);
        $this->assertContains('Dragonborn', $names);
        $this->assertNotContains('Gnome', $names);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_races_by_size_small(): void
    {
        // Arrange: Create races with different sizes
        $smallSize = Size::where('code', 'S')->first();
        $mediumSize = Size::where('code', 'M')->first();

        $halfling = Race::factory()->create([
            'name' => 'Halfling',
            'size_id' => $smallSize->id,
        ]);
        $halfling->load('size')->searchable();

        $gnome = Race::factory()->create([
            'name' => 'Gnome',
            'size_id' => $smallSize->id,
        ]);
        $gnome->load('size')->searchable();

        $human = Race::factory()->create([
            'name' => 'Human',
            'size_id' => $mediumSize->id,
        ]);
        $human->load('size')->searchable();

        sleep(1); // Wait for Meilisearch indexing

        // Act: Filter by size_code = S using Meilisearch
        $response = $this->getJson('/api/v1/races?filter=size_code = S');

        // Assert: Only small races returned
        $response->assertOk();
        $data = $response->json('data');

        $this->assertCount(2, $data);
        $names = collect($data)->pluck('name')->toArray();
        $this->assertContains('Halfling', $names);
        $this->assertContains('Gnome', $names);
        $this->assertNotContains('Human', $names);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_races_by_size_medium(): void
    {
        // Arrange: Create races with different sizes
        $smallSize = Size::where('code', 'S')->first();
        $mediumSize = Size::where('code', 'M')->first();

        $human = Race::factory()->create([
            'name' => 'Human',
            'size_id' => $mediumSize->id,
        ]);
        $human->load('size')->searchable();

        $elf = Race::factory()->create([
            'name' => 'Elf',
            'size_id' => $mediumSize->id,
        ]);
        $elf->load('size')->searchable();

        $halfling = Race::factory()->create([
            'name' => 'Halfling',
            'size_id' => $smallSize->id,
        ]);
        $halfling->load('size')->searchable();

        sleep(1); // Wait for Meilisearch indexing

        // Act: Filter by size_code = M using Meilisearch
        $response = $this->getJson('/api/v1/races?filter=size_code = M');

        // Assert: Only medium races returned
        $response->assertOk();
        $data = $response->json('data');

        $this->assertCount(2, $data);
        $names = collect($data)->pluck('name')->toArray();
        $this->assertContains('Human', $names);
        $this->assertContains('Elf', $names);
        $this->assertNotContains('Halfling', $names);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_races_by_min_speed_35(): void
    {
        // Arrange: Create races with different speeds
        $woodElf = Race::factory()->create([
            'name' => 'Wood Elf',
            'speed' => 35,
        ]);
        $woodElf->searchable();

        $human = Race::factory()->create([
            'name' => 'Human',
            'speed' => 30,
        ]);
        $human->searchable();

        $dwarf = Race::factory()->create([
            'name' => 'Dwarf',
            'speed' => 25,
        ]);
        $dwarf->searchable();

        sleep(1); // Wait for Meilisearch indexing

        // Act: Filter by speed >= 35 using Meilisearch
        $response = $this->getJson('/api/v1/races?filter=speed >= 35');

        // Assert: Only races with speed >= 35 returned
        $response->assertOk();
        $data = $response->json('data');

        $this->assertCount(1, $data);
        $this->assertEquals('Wood Elf', $data[0]['name']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_races_by_has_darkvision_true(): void
    {
        // Arrange: Create races with and without darkvision tag
        $dwarf = Race::factory()->create(['name' => 'Dwarf']);
        $dwarf->attachTag('darkvision');
        CharacterTrait::factory()->create([
            'reference_type' => Race::class,
            'reference_id' => $dwarf->id,
            'name' => 'Darkvision',
            'description' => 'You have superior vision in dark and dim conditions.',
        ]);
        $dwarf->fresh()->searchable();

        $elf = Race::factory()->create(['name' => 'Elf']);
        $elf->attachTag('darkvision');
        CharacterTrait::factory()->create([
            'reference_type' => Race::class,
            'reference_id' => $elf->id,
            'name' => 'Darkvision',
            'description' => 'Accustomed to twilit forests and the night sky.',
        ]);
        $elf->fresh()->searchable();

        $tiefling = Race::factory()->create(['name' => 'Tiefling']);
        $tiefling->attachTag('darkvision');
        CharacterTrait::factory()->create([
            'reference_type' => Race::class,
            'reference_id' => $tiefling->id,
            'name' => 'Darkvision',
            'description' => 'Thanks to your infernal heritage, you have superior vision.',
        ]);
        $tiefling->fresh()->searchable();

        $human = Race::factory()->create(['name' => 'Human']);
        $human->searchable();

        sleep(1); // Wait for Meilisearch indexing

        // Act: Filter by tag_slugs IN [darkvision] using Meilisearch
        $response = $this->getJson('/api/v1/races?filter=tag_slugs IN [darkvision]');

        // Assert: Only races with darkvision returned
        $response->assertOk();
        $data = $response->json('data');

        $this->assertCount(3, $data);
        $names = collect($data)->pluck('name')->toArray();
        $this->assertContains('Dwarf', $names);
        $this->assertContains('Elf', $names);
        $this->assertContains('Tiefling', $names);
        $this->assertNotContains('Human', $names);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_races_by_combined_ability_bonus_and_has_darkvision(): void
    {
        // Arrange: Create races with INT bonus and darkvision
        $intAbility = AbilityScore::where('code', 'INT')->first();

        $gnome = Race::factory()->create(['name' => 'Gnome']);
        $gnome->attachTag('darkvision');
        Modifier::factory()->create([
            'reference_type' => Race::class,
            'reference_id' => $gnome->id,
            'modifier_category' => 'ability_score',
            'ability_score_id' => $intAbility->id,
            'value' => 2,
        ]);
        CharacterTrait::factory()->create([
            'reference_type' => Race::class,
            'reference_id' => $gnome->id,
            'name' => 'Darkvision',
            'description' => 'You have superior vision in dark and dim conditions.',
        ]);
        $gnome->fresh()->searchable();

        $highElf = Race::factory()->create(['name' => 'High Elf']);
        $highElf->attachTag('darkvision');
        Modifier::factory()->create([
            'reference_type' => Race::class,
            'reference_id' => $highElf->id,
            'modifier_category' => 'ability_score',
            'ability_score_id' => $intAbility->id,
            'value' => 1,
        ]);
        CharacterTrait::factory()->create([
            'reference_type' => Race::class,
            'reference_id' => $highElf->id,
            'name' => 'Darkvision',
            'description' => 'Accustomed to twilit forests.',
        ]);
        $highElf->fresh()->searchable();

        // Create race with INT but no darkvision
        $human = Race::factory()->create(['name' => 'Human']);
        Modifier::factory()->create([
            'reference_type' => Race::class,
            'reference_id' => $human->id,
            'modifier_category' => 'ability_score',
            'ability_score_id' => $intAbility->id,
            'value' => 1,
        ]);
        $human->fresh()->searchable();

        // Create race with darkvision but no INT
        $strAbility = AbilityScore::where('code', 'STR')->first();
        $dwarf = Race::factory()->create(['name' => 'Dwarf']);
        $dwarf->attachTag('darkvision');
        Modifier::factory()->create([
            'reference_type' => Race::class,
            'reference_id' => $dwarf->id,
            'modifier_category' => 'ability_score',
            'ability_score_id' => $strAbility->id,
            'value' => 2,
        ]);
        CharacterTrait::factory()->create([
            'reference_type' => Race::class,
            'reference_id' => $dwarf->id,
            'name' => 'Darkvision',
            'description' => 'You have superior vision.',
        ]);
        $dwarf->fresh()->searchable();

        sleep(1); // Wait for Meilisearch indexing

        // Act: Filter by ability_int_bonus > 0 AND tag_slugs IN [darkvision] using Meilisearch
        $response = $this->getJson('/api/v1/races?filter=ability_int_bonus > 0 AND tag_slugs IN [darkvision]');

        // Assert: Only races with both INT bonus and darkvision returned
        $response->assertOk();
        $data = $response->json('data');

        $this->assertCount(2, $data);
        $names = collect($data)->pluck('name')->toArray();
        $this->assertContains('Gnome', $names);
        $this->assertContains('High Elf', $names);
        $this->assertNotContains('Human', $names);
        $this->assertNotContains('Dwarf', $names);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_validates_filter_parameter_with_invalid_syntax(): void
    {
        // Act: Send invalid filter syntax (this will be caught by Meilisearch, not validation)
        // In Meilisearch-first architecture, invalid filter syntax returns 422 from service layer
        $response = $this->getJson('/api/v1/races?filter=invalid syntax here!!!');

        // Assert: Either validation error or Meilisearch error (both return 422)
        $response->assertStatus(422);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_accepts_valid_size_filter(): void
    {
        // Arrange: Create a small race
        $smallSize = Size::where('code', 'S')->first();
        $halfling = Race::factory()->create([
            'name' => 'Halfling',
            'size_id' => $smallSize->id,
        ]);
        $halfling->load('size')->searchable();

        sleep(1); // Wait for Meilisearch indexing

        // Act: Send valid size filter
        $response = $this->getJson('/api/v1/races?filter=size_code = S');

        // Assert: Success
        $response->assertOk();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_accepts_valid_speed_filter(): void
    {
        // Arrange: Create a fast race
        $woodElf = Race::factory()->create([
            'name' => 'Wood Elf',
            'speed' => 35,
        ]);
        $woodElf->searchable();

        sleep(1); // Wait for Meilisearch indexing

        // Act: Send valid speed filter
        $response = $this->getJson('/api/v1/races?filter=speed >= 30');

        // Assert: Success
        $response->assertOk();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_accepts_valid_darkvision_filter(): void
    {
        // Arrange: Create a race with darkvision
        $dwarf = Race::factory()->create(['name' => 'Dwarf']);
        $dwarf->attachTag('darkvision');
        $dwarf->fresh()->searchable();

        sleep(1); // Wait for Meilisearch indexing

        // Act: Send valid darkvision filter
        $response = $this->getJson('/api/v1/races?filter=tag_slugs IN [darkvision]');

        // Assert: Success
        $response->assertOk();
    }
}
