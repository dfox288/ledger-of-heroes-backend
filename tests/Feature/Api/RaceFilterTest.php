<?php

namespace Tests\Feature\Api;

use App\Models\Language;
use App\Models\ProficiencyType;
use App\Models\Race;
use App\Models\Skill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RaceFilterTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    #[Test]
    public function it_filters_races_by_granted_proficiency()
    {
        $mountainDwarf = Race::factory()->create(['name' => 'Mountain Dwarf']);
        $mountainDwarf->proficiencies()->create([
            'proficiency_name' => 'Light Armor',
            'proficiency_type' => 'armor',
        ]);

        $elf = Race::factory()->create(['name' => 'Elf']);

        $response = $this->getJson('/api/v1/races?grants_proficiency=light armor');
        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Mountain Dwarf');
    }

    #[Test]
    public function it_filters_races_by_spoken_language()
    {
        $elvish = Language::where('name', 'Elvish')->first();

        $elf = Race::factory()->create(['name' => 'Elf']);
        $elf->languages()->create([
            'language_id' => $elvish->id,
            'is_choice' => false,
        ]);

        $dwarf = Race::factory()->create(['name' => 'Dwarf']);

        $response = $this->getJson('/api/v1/races?speaks_language=elvish');
        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Elf');
    }

    #[Test]
    public function it_filters_races_by_language_choice_count()
    {
        $halfElf = Race::factory()->create(['name' => 'Half-Elf']);
        $halfElf->languages()->create([
            'language_id' => null,
            'is_choice' => true,
        ]);

        $human = Race::factory()->create(['name' => 'Human']);
        // Create 2 separate choice records for 2 language choices
        $human->languages()->create([
            'language_id' => null,
            'is_choice' => true,
        ]);
        $human->languages()->create([
            'language_id' => null,
            'is_choice' => true,
        ]);

        $dwarf = Race::factory()->create(['name' => 'Dwarf']);

        // Filter for races granting 1 language choice
        $response = $this->getJson('/api/v1/races?language_choice_count=1');
        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Half-Elf');

        // Filter for races granting 2 language choices
        $response = $this->getJson('/api/v1/races?language_choice_count=2');
        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Human');
    }

    #[Test]
    public function it_filters_races_granting_any_languages()
    {
        $elf = Race::factory()->create(['name' => 'Elf']);
        $elf->languages()->create([
            'language_id' => Language::where('name', 'Elvish')->first()->id,
            'is_choice' => false,
        ]);

        $dwarf = Race::factory()->create(['name' => 'Dwarf']);
        // No languages

        $response = $this->getJson('/api/v1/races?grants_languages=true');
        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Elf');
    }

    #[Test]
    public function it_filters_races_by_granted_skill()
    {
        $insight = Skill::where('name', 'Insight')->first();

        $human = Race::factory()->create(['name' => 'Human']);
        $human->proficiencies()->create([
            'proficiency_type' => 'skill',
            'proficiency_name' => 'Insight',
            'skill_id' => $insight?->id,
        ]);

        $elf = Race::factory()->create(['name' => 'Elf']);

        $response = $this->getJson('/api/v1/races?grants_skill=insight');
        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Human');
    }

    #[Test]
    public function it_filters_races_by_proficiency_type_category()
    {
        // Create proficiency type with unique name for testing
        $proficiencyType = ProficiencyType::firstOrCreate(
            ['name' => 'Longsword'],
            ['category' => 'martial weapon']
        );

        $elf = Race::factory()->create(['name' => 'Elf']);
        $elf->proficiencies()->create([
            'proficiency_type' => 'weapon',
            'proficiency_type_id' => $proficiencyType->id,
            'proficiency_name' => $proficiencyType->name,
        ]);

        $dwarf = Race::factory()->create(['name' => 'Dwarf']);
        // No proficiencies

        // Test filtering by proficiency type name (not category)
        $response = $this->getJson('/api/v1/races?grants_proficiency_type=longsword');
        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Elf');
    }

    #[Test]
    public function it_combines_proficiency_and_language_filters()
    {
        $elvish = Language::where('name', 'Elvish')->first();

        // Use seeded proficiency type
        $proficiencyType = ProficiencyType::where('name', 'LIKE', '%Longsword%')->first();

        // If not found, create one manually
        if (! $proficiencyType) {
            $proficiencyType = ProficiencyType::create([
                'name' => 'Longsword',
                'category' => 'martial weapon',
            ]);
        }

        // Elf with both language and proficiency
        $elf = Race::factory()->create(['name' => 'Elf']);
        $elf->languages()->create([
            'language_id' => $elvish->id,
            'is_choice' => false,
        ]);
        $elf->proficiencies()->create([
            'proficiency_type' => 'weapon',
            'proficiency_type_id' => $proficiencyType->id,
            'proficiency_name' => 'Longsword',
        ]);

        // Half-Elf with language only
        $halfElf = Race::factory()->create(['name' => 'Half-Elf']);
        $halfElf->languages()->create([
            'language_id' => $elvish->id,
            'is_choice' => false,
        ]);

        // Dwarf with neither
        $dwarf = Race::factory()->create(['name' => 'Dwarf']);

        // Filter for races with both
        $response = $this->getJson('/api/v1/races?speaks_language=elvish&grants_proficiency=longsword');
        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Elf');
    }

    #[Test]
    public function it_handles_case_insensitive_language_searches()
    {
        $elvish = Language::where('name', 'Elvish')->first();

        $elf = Race::factory()->create(['name' => 'Elf']);
        $elf->languages()->create([
            'language_id' => $elvish->id,
            'is_choice' => false,
        ]);

        // Test with lowercase
        $response = $this->getJson('/api/v1/races?speaks_language=elvish');
        $response->assertOk();
        $response->assertJsonCount(1, 'data');

        // Test with uppercase
        $response = $this->getJson('/api/v1/races?speaks_language=ELVISH');
        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    #[Test]
    public function it_excludes_language_choices_from_spoken_language_filter()
    {
        $elvish = Language::where('name', 'Elvish')->first();

        // Race with fixed Elvish
        $elf = Race::factory()->create(['name' => 'Elf']);
        $elf->languages()->create([
            'language_id' => $elvish->id,
            'is_choice' => false,
        ]);

        // Race with language choice (not fixed Elvish)
        $human = Race::factory()->create(['name' => 'Human']);
        $human->languages()->create([
            'language_id' => null,
            'is_choice' => true,
            'choice_count' => 1,
        ]);

        // Should only return races with FIXED Elvish
        $response = $this->getJson('/api/v1/races?speaks_language=elvish');
        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Elf');
    }

    #[Test]
    public function it_paginates_filtered_race_results()
    {
        $elvish = Language::where('name', 'Elvish')->first();

        // Create 25 races with Elvish
        for ($i = 1; $i <= 25; $i++) {
            $race = Race::factory()->create(['name' => "Elf Subrace {$i}"]);
            $race->languages()->create([
                'language_id' => $elvish->id,
                'is_choice' => false,
            ]);
        }

        $response = $this->getJson('/api/v1/races?speaks_language=elvish&per_page=10');
        $response->assertOk();
        $response->assertJsonCount(10, 'data');
        $response->assertJsonPath('meta.total', 25);
        $response->assertJsonPath('meta.per_page', 10);
    }

    #[Test]
    public function it_returns_empty_when_no_races_match_filters()
    {
        $dwarf = Race::factory()->create(['name' => 'Dwarf']);

        $response = $this->getJson('/api/v1/races?speaks_language=infernal');
        $response->assertOk();
        $response->assertJsonCount(0, 'data');

        $response = $this->getJson('/api/v1/races?grants_proficiency=heavy armor');
        $response->assertOk();
        $response->assertJsonCount(0, 'data');
    }
}
