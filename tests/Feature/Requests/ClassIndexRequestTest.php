<?php

namespace Tests\Feature\Requests;

use App\Models\AbilityScore;
use App\Models\CharacterClass;
use App\Models\ProficiencyType;
use App\Models\Skill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClassIndexRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Only seed if database is empty
        if (AbilityScore::count() === 0) {
            $this->seed(\Database\Seeders\AbilityScoreSeeder::class);
        }
        if (ProficiencyType::count() === 0) {
            $this->seed(\Database\Seeders\ProficiencyTypeSeeder::class);
        }
        if (Skill::count() === 0) {
            $this->seed(\Database\Seeders\SkillSeeder::class);
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_validates_grants_proficiency_exists(): void
    {
        CharacterClass::factory()->create(['name' => 'Fighter']);

        $response = $this->getJson('/api/v1/classes?grants_proficiency=longsword');
        $response->assertStatus(200);

        $response = $this->getJson('/api/v1/classes?grants_proficiency=invalid_proficiency_name');
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('grants_proficiency');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_validates_grants_skill_exists(): void
    {
        CharacterClass::factory()->create(['name' => 'Rogue']);

        $validSkill = strtolower(Skill::first()->name);
        $response = $this->getJson("/api/v1/classes?grants_skill={$validSkill}");
        $response->assertStatus(200);

        $response = $this->getJson('/api/v1/classes?grants_skill=invalid_skill_name');
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('grants_skill');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_validates_grants_saving_throw_exists(): void
    {
        CharacterClass::factory()->create(['name' => 'Wizard']);

        $validAbility = strtolower(AbilityScore::first()->code);
        $response = $this->getJson("/api/v1/classes?grants_saving_throw={$validAbility}");
        $response->assertStatus(200);

        $response = $this->getJson('/api/v1/classes?grants_saving_throw=INVALID');
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('grants_saving_throw');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_whitelists_sortable_columns(): void
    {
        CharacterClass::factory()->create(['name' => 'Barbarian']);

        // Valid sortable columns
        $validColumns = ['name', 'hit_die', 'created_at', 'updated_at'];
        foreach ($validColumns as $column) {
            $response = $this->getJson("/api/v1/classes?sort_by={$column}");
            $response->assertStatus(200);
        }

        // Invalid column
        $response = $this->getJson('/api/v1/classes?sort_by=invalid_column');
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('sort_by');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_validates_per_page_limit(): void
    {
        CharacterClass::factory()->create(['name' => 'Paladin']);

        // Valid per_page values
        $response = $this->getJson('/api/v1/classes?per_page=10');
        $response->assertStatus(200);

        $response = $this->getJson('/api/v1/classes?per_page=100');
        $response->assertStatus(200);

        // Invalid: too low
        $response = $this->getJson('/api/v1/classes?per_page=0');
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('per_page');

        // Invalid: too high
        $response = $this->getJson('/api/v1/classes?per_page=101');
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('per_page');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_validates_base_only_is_boolean(): void
    {
        CharacterClass::factory()->create(['name' => 'Monk']);

        $response = $this->getJson('/api/v1/classes?base_only=1');
        $response->assertStatus(200);

        $response = $this->getJson('/api/v1/classes?base_only=0');
        $response->assertStatus(200);

        $response = $this->getJson('/api/v1/classes?base_only=invalid');
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('base_only');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_validates_search_max_length(): void
    {
        CharacterClass::factory()->create(['name' => 'Druid']);

        $response = $this->getJson('/api/v1/classes?search='.str_repeat('a', 255));
        $response->assertStatus(200);

        $response = $this->getJson('/api/v1/classes?search='.str_repeat('a', 256));
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('search');
    }
}
