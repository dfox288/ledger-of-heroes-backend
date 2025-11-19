<?php

namespace Tests\Feature\Migrations;

use App\Models\Background;
use App\Models\Proficiency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProficienciesChoiceSupportTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_has_choice_support_columns(): void
    {
        $this->assertTrue(Schema::hasColumn('proficiencies', 'is_choice'));
        $this->assertTrue(Schema::hasColumn('proficiencies', 'quantity'));
    }

    #[Test]
    public function it_has_index_on_is_choice(): void
    {
        $indexes = Schema::getIndexes('proficiencies');

        $isChoiceIndex = collect($indexes)->first(function ($index) {
            return in_array('is_choice', $index['columns']);
        });

        $this->assertNotNull($isChoiceIndex, 'Index on is_choice column not found');
    }

    #[Test]
    public function proficiency_factory_supports_choices(): void
    {
        $background = Background::factory()->create();

        $prof = Proficiency::factory()
            ->forEntity(Background::class, $background->id)
            ->asChoice(1)
            ->create([
                'proficiency_name' => "artisan's tools",
                'proficiency_type' => 'tool',
            ]);

        $this->assertTrue($prof->is_choice);
        $this->assertEquals(1, $prof->quantity);
    }

    #[Test]
    public function existing_proficiencies_have_default_values(): void
    {
        $background = Background::factory()->create();

        $prof = Proficiency::factory()
            ->forEntity(Background::class, $background->id)
            ->create([
                'proficiency_name' => 'Insight',
                'proficiency_type' => 'skill',
            ]);

        // Should default to false/1
        $this->assertFalse($prof->is_choice);
        $this->assertEquals(1, $prof->quantity);
    }
}
