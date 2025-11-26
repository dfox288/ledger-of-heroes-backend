<?php

namespace Tests\Feature\Models;

use App\Models\EntitySource;
use App\Models\Feat;
use App\Models\Modifier;
use App\Models\Proficiency;
use App\Models\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FeatModelTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function feat_can_be_created_with_factory()
    {
        $feat = Feat::factory()->create();

        $this->assertInstanceOf(Feat::class, $feat);
        $this->assertNotNull($feat->id);
        $this->assertNotNull($feat->name);
        $this->assertNotNull($feat->slug);
    }

    #[Test]
    public function feat_does_not_have_timestamps()
    {
        $feat = Feat::factory()->create();

        $this->assertFalse($feat->usesTimestamps());
    }

    #[Test]
    public function feat_has_sources_relationship()
    {
        // Create source if not exists
        $source = Source::where('code', 'PHB')->first()
            ?? Source::factory()->create(['code' => 'PHB', 'name' => 'Player\'s Handbook']);

        $feat = Feat::factory()->create();

        EntitySource::factory()
            ->forEntity(Feat::class, $feat->id)
            ->create(['source_id' => $source->id]);

        $this->assertCount(1, $feat->sources);
        $this->assertInstanceOf(EntitySource::class, $feat->sources->first());
    }

    #[Test]
    public function feat_has_modifiers_relationship()
    {
        $feat = Feat::factory()->create();

        Modifier::create([
            'reference_type' => Feat::class,
            'reference_id' => $feat->id,
            'modifier_category' => 'ability_score',
            'value' => '+1',
        ]);

        $feat->refresh();

        $this->assertCount(1, $feat->modifiers);
        $this->assertInstanceOf(Modifier::class, $feat->modifiers->first());
    }

    #[Test]
    public function feat_has_proficiencies_relationship()
    {
        $feat = Feat::factory()->create();

        Proficiency::create([
            'reference_type' => Feat::class,
            'reference_id' => $feat->id,
            'proficiency_type' => 'weapon',
            'proficiency_name' => 'Longsword',
            'grants' => true,
        ]);

        $feat->refresh();

        $this->assertCount(1, $feat->proficiencies);
        $this->assertInstanceOf(Proficiency::class, $feat->proficiencies->first());
    }

    #[Test]
    public function feat_has_fillable_attributes()
    {
        $feat = Feat::factory()->create([
            'name' => 'Test Feat',
            'slug' => 'test-feat',
            'prerequisites_text' => 'Strength 13 or higher',
            'description' => 'Test description',
        ]);

        $this->assertEquals('Test Feat', $feat->name);
        $this->assertEquals('test-feat', $feat->slug);
        $this->assertEquals('Strength 13 or higher', $feat->prerequisites_text);
        $this->assertEquals('Test description', $feat->description);
    }
}
