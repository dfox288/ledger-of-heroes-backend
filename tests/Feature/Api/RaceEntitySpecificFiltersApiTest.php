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

        $gnome = Race::factory()->create(['name' => 'Gnome']);
        Modifier::factory()->create([
            'reference_type' => Race::class,
            'reference_id' => $gnome->id,
            'modifier_category' => 'ability_score',
            'ability_score_id' => $intAbility->id,
            'value' => 2,
        ]);

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

        // Act: Filter by ability_bonus=INT
        $response = $this->getJson('/api/v1/races?ability_bonus=INT');

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

        $dragonborn = Race::factory()->create(['name' => 'Dragonborn']);
        Modifier::factory()->create([
            'reference_type' => Race::class,
            'reference_id' => $dragonborn->id,
            'modifier_category' => 'ability_score',
            'ability_score_id' => $strAbility->id,
            'value' => 2,
        ]);

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

        // Act: Filter by ability_bonus=STR
        $response = $this->getJson('/api/v1/races?ability_bonus=STR');

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

        $gnome = Race::factory()->create([
            'name' => 'Gnome',
            'size_id' => $smallSize->id,
        ]);

        $human = Race::factory()->create([
            'name' => 'Human',
            'size_id' => $mediumSize->id,
        ]);

        // Act: Filter by size=S
        $response = $this->getJson('/api/v1/races?size=S');

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

        $elf = Race::factory()->create([
            'name' => 'Elf',
            'size_id' => $mediumSize->id,
        ]);

        $halfling = Race::factory()->create([
            'name' => 'Halfling',
            'size_id' => $smallSize->id,
        ]);

        // Act: Filter by size=M
        $response = $this->getJson('/api/v1/races?size=M');

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

        $human = Race::factory()->create([
            'name' => 'Human',
            'speed' => 30,
        ]);

        $dwarf = Race::factory()->create([
            'name' => 'Dwarf',
            'speed' => 25,
        ]);

        // Act: Filter by min_speed=35
        $response = $this->getJson('/api/v1/races?min_speed=35');

        // Assert: Only races with speed >= 35 returned
        $response->assertOk();
        $data = $response->json('data');

        $this->assertCount(1, $data);
        $this->assertEquals('Wood Elf', $data[0]['name']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_races_by_has_darkvision_true(): void
    {
        // Arrange: Create races with and without darkvision
        $dwarf = Race::factory()->create(['name' => 'Dwarf']);
        CharacterTrait::factory()->create([
            'reference_type' => Race::class,
            'reference_id' => $dwarf->id,
            'name' => 'Darkvision',
            'description' => 'You have superior vision in dark and dim conditions.',
        ]);

        $elf = Race::factory()->create(['name' => 'Elf']);
        CharacterTrait::factory()->create([
            'reference_type' => Race::class,
            'reference_id' => $elf->id,
            'name' => 'Darkvision',
            'description' => 'Accustomed to twilit forests and the night sky.',
        ]);

        $tiefling = Race::factory()->create(['name' => 'Tiefling']);
        CharacterTrait::factory()->create([
            'reference_type' => Race::class,
            'reference_id' => $tiefling->id,
            'name' => 'Darkvision',
            'description' => 'Thanks to your infernal heritage, you have superior vision.',
        ]);

        $human = Race::factory()->create(['name' => 'Human']);
        // No darkvision trait

        // Act: Filter by has_darkvision=true
        $response = $this->getJson('/api/v1/races?has_darkvision=true');

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

        $highElf = Race::factory()->create(['name' => 'High Elf']);
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

        // Create race with INT but no darkvision
        $human = Race::factory()->create(['name' => 'Human']);
        Modifier::factory()->create([
            'reference_type' => Race::class,
            'reference_id' => $human->id,
            'modifier_category' => 'ability_score',
            'ability_score_id' => $intAbility->id,
            'value' => 1,
        ]);

        // Create race with darkvision but no INT
        $strAbility = AbilityScore::where('code', 'STR')->first();
        $dwarf = Race::factory()->create(['name' => 'Dwarf']);
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

        // Act: Filter by ability_bonus=INT AND has_darkvision=true
        $response = $this->getJson('/api/v1/races?ability_bonus=INT&has_darkvision=true');

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
    public function it_validates_ability_bonus_parameter(): void
    {
        // Act: Send invalid ability_bonus value
        $response = $this->getJson('/api/v1/races?ability_bonus=INVALID');

        // Assert: Validation error
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('ability_bonus');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_validates_size_parameter(): void
    {
        // Act: Send invalid size value
        $response = $this->getJson('/api/v1/races?size=INVALID');

        // Assert: Validation error
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('size');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_validates_min_speed_parameter(): void
    {
        // Act: Send invalid min_speed value
        $response = $this->getJson('/api/v1/races?min_speed=-5');

        // Assert: Validation error
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('min_speed');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_validates_has_darkvision_parameter(): void
    {
        // Act: Send invalid has_darkvision value
        $response = $this->getJson('/api/v1/races?has_darkvision=invalid');

        // Assert: Validation error
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('has_darkvision');
    }
}
