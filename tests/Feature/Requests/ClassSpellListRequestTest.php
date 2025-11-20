<?php

namespace Tests\Feature\Requests;

use App\Models\CharacterClass;
use App\Models\Spell;
use App\Models\SpellSchool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClassSpellListRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Only seed if database is empty
        if (SpellSchool::count() === 0) {
            $this->seed(\Database\Seeders\SpellSchoolSeeder::class);
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_validates_spell_level_range(): void
    {
        $class = CharacterClass::factory()->create(['name' => 'Wizard']);
        $spell = Spell::factory()->create(['level' => 1]);
        $class->spells()->attach($spell->id);

        // Valid levels (0-9)
        $response = $this->getJson("/api/v1/classes/{$class->id}/spells?level=0");
        $response->assertStatus(200);

        $response = $this->getJson("/api/v1/classes/{$class->id}/spells?level=9");
        $response->assertStatus(200);

        // Invalid: negative
        $response = $this->getJson("/api/v1/classes/{$class->id}/spells?level=-1");
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('level');

        // Invalid: too high
        $response = $this->getJson("/api/v1/classes/{$class->id}/spells?level=10");
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('level');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_validates_school_exists(): void
    {
        $class = CharacterClass::factory()->create(['name' => 'Sorcerer']);
        $spell = Spell::factory()->create();
        $class->spells()->attach($spell->id);

        $validSchool = SpellSchool::first();

        // Valid school ID
        $response = $this->getJson("/api/v1/classes/{$class->id}/spells?school={$validSchool->id}");
        $response->assertStatus(200);

        // Invalid school ID
        $response = $this->getJson("/api/v1/classes/{$class->id}/spells?school=9999");
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('school');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_validates_boolean_filters(): void
    {
        $class = CharacterClass::factory()->create(['name' => 'Cleric']);
        $spell = Spell::factory()->create();
        $class->spells()->attach($spell->id);

        // Valid boolean values for concentration
        $response = $this->getJson("/api/v1/classes/{$class->id}/spells?concentration=1");
        $response->assertStatus(200);

        $response = $this->getJson("/api/v1/classes/{$class->id}/spells?concentration=0");
        $response->assertStatus(200);

        // Valid boolean values for ritual
        $response = $this->getJson("/api/v1/classes/{$class->id}/spells?ritual=1");
        $response->assertStatus(200);

        $response = $this->getJson("/api/v1/classes/{$class->id}/spells?ritual=0");
        $response->assertStatus(200);

        // Invalid: non-boolean
        $response = $this->getJson("/api/v1/classes/{$class->id}/spells?concentration=invalid");
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('concentration');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_validates_sortable_columns(): void
    {
        $class = CharacterClass::factory()->create(['name' => 'Druid']);
        $spell = Spell::factory()->create();
        $class->spells()->attach($spell->id);

        // Valid sort columns (spells table doesn't have timestamps)
        $validColumns = ['name', 'level'];
        foreach ($validColumns as $column) {
            $response = $this->getJson("/api/v1/classes/{$class->id}/spells?sort_by={$column}");
            $response->assertStatus(200);
        }

        // Invalid column
        $response = $this->getJson("/api/v1/classes/{$class->id}/spells?sort_by=invalid_column");
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('sort_by');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_validates_per_page_limit(): void
    {
        $class = CharacterClass::factory()->create(['name' => 'Bard']);
        $spell = Spell::factory()->create();
        $class->spells()->attach($spell->id);

        // Valid per_page values
        $response = $this->getJson("/api/v1/classes/{$class->id}/spells?per_page=10");
        $response->assertStatus(200);

        $response = $this->getJson("/api/v1/classes/{$class->id}/spells?per_page=100");
        $response->assertStatus(200);

        // Invalid: too low
        $response = $this->getJson("/api/v1/classes/{$class->id}/spells?per_page=0");
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('per_page');

        // Invalid: too high
        $response = $this->getJson("/api/v1/classes/{$class->id}/spells?per_page=101");
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('per_page');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_validates_search_max_length(): void
    {
        $class = CharacterClass::factory()->create(['name' => 'Warlock']);
        $spell = Spell::factory()->create();
        $class->spells()->attach($spell->id);

        $response = $this->getJson("/api/v1/classes/{$class->id}/spells?search=".str_repeat('a', 255));
        $response->assertStatus(200);

        $response = $this->getJson("/api/v1/classes/{$class->id}/spells?search=".str_repeat('a', 256));
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('search');
    }
}
