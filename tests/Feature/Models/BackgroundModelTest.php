<?php

namespace Tests\Feature\Models;

use App\Models\Background;
use App\Models\CharacterTrait;
use App\Models\EntitySource;
use App\Models\Proficiency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BackgroundModelTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function background_has_traits_relationship()
    {
        $background = Background::create(['name' => 'Test Background']);

        CharacterTrait::create([
            'reference_type' => Background::class,
            'reference_id' => $background->id,
            'name' => 'Description',
            'description' => 'Test description',
        ]);

        $this->assertCount(1, $background->fresh()->traits);
        $this->assertEquals('Description', $background->traits->first()->name);
    }

    #[Test]
    public function background_has_proficiencies_relationship()
    {
        $background = Background::create(['name' => 'Test Background']);

        Proficiency::create([
            'reference_type' => Background::class,
            'reference_id' => $background->id,
            'proficiency_type' => 'skill',
            'proficiency_name' => 'Insight',
        ]);

        $this->assertCount(1, $background->fresh()->proficiencies);
        $this->assertEquals('Insight', $background->proficiencies->first()->proficiency_name);
    }

    #[Test]
    public function background_has_sources_relationship()
    {
        $background = Background::create(['name' => 'Test Background']);

        EntitySource::create([
            'reference_type' => Background::class,
            'reference_id' => $background->id,
            'source_id' => 1, // PHB (seeded)
            'pages' => '127',
        ]);

        $this->assertCount(1, $background->fresh()->sources);
        $this->assertEquals('127', $background->sources->first()->pages);
    }
}
