<?php

namespace Tests\Unit\Factories;

use App\Models\Background;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BackgroundFactoryTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_creates_background_with_valid_data()
    {
        $background = Background::factory()->create(['name' => 'Acolyte']);

        $this->assertDatabaseHas('backgrounds', [
            'name' => 'Acolyte',
        ]);
    }

    #[Test]
    public function it_creates_background_with_traits_state()
    {
        $background = Background::factory()->withTraits()->create();

        $this->assertCount(3, $background->traits);
        $this->assertTrue($background->traits->contains('name', 'Description'));
        $this->assertTrue($background->traits->contains('category', 'feature'));
        $this->assertTrue($background->traits->contains('category', 'characteristics'));
    }

    #[Test]
    public function it_creates_background_with_proficiencies_state()
    {
        $background = Background::factory()->withProficiencies()->create();

        $this->assertCount(3, $background->proficiencies); // 2 skills + 1 language
        $this->assertEquals(2, $background->proficiencies->where('proficiency_type', 'skill')->count());
        $this->assertEquals(1, $background->proficiencies->where('proficiency_type', 'language')->count());
    }

    #[Test]
    public function it_creates_complete_background()
    {
        $background = Background::factory()->complete()->create();

        $this->assertCount(3, $background->traits);
        $this->assertCount(3, $background->proficiencies);
        $this->assertCount(1, $background->sources);
    }
}
