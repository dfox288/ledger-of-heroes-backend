<?php

namespace Tests\Feature\Models;

use App\Models\Background;
use App\Models\CharacterTrait;
use App\Models\EntitySource;
use App\Models\Proficiency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('feature-db')]
class BackgroundModelTest extends TestCase
{
    use RefreshDatabase;

    protected $seeder = \Database\Seeders\LookupSeeder::class;

    #[Test]
    public function background_has_traits_relationship()
    {
        $background = Background::create(['slug' => 'test-background', 'name' => 'Test Background']);

        CharacterTrait::create([
            'reference_type' => Background::class,
            'reference_id' => $background->id,
            'name' => 'Description',
            'description' => 'Test description',
        ]);

        $freshBackground = $background->fresh();
        $this->assertCount(1, $freshBackground->traits);
        $this->assertEquals('Description', $freshBackground->traits->first()->name);
    }

    #[Test]
    public function background_has_proficiencies_relationship()
    {
        $background = Background::create(['slug' => 'test-background', 'name' => 'Test Background']);

        Proficiency::create([
            'reference_type' => Background::class,
            'reference_id' => $background->id,
            'proficiency_type' => 'skill',
            'proficiency_name' => 'Insight',
        ]);

        $freshBackground = $background->fresh();
        $this->assertCount(1, $freshBackground->proficiencies);
        $this->assertEquals('Insight', $freshBackground->proficiencies->first()->proficiency_name);
    }

    #[Test]
    public function background_has_sources_relationship()
    {
        $background = Background::create(['slug' => 'test-background', 'name' => 'Test Background']);

        EntitySource::create([
            'reference_type' => Background::class,
            'reference_id' => $background->id,
            'source_id' => 1, // PHB (seeded)
            'pages' => '127',
        ]);

        $fresh = $background->fresh();
        $this->assertCount(1, $fresh->sources);
        $this->assertEquals('127', $fresh->sources->first()->pages);
    }

    #[Test]
    public function feature_name_returns_cleaned_feature_trait_name()
    {
        $background = Background::create(['slug' => 'acolyte', 'name' => 'Acolyte']);

        // Create the feature trait with "Feature: " prefix
        CharacterTrait::create([
            'reference_type' => Background::class,
            'reference_id' => $background->id,
            'name' => 'Feature: Shelter of the Faithful',
            'category' => 'feature',
            'description' => 'As an acolyte, you command the respect...',
        ]);

        $this->assertEquals('Shelter of the Faithful', $background->fresh()->feature_name);
    }

    #[Test]
    public function feature_description_returns_feature_trait_description()
    {
        $background = Background::create(['slug' => 'acolyte', 'name' => 'Acolyte']);

        $featureDescription = 'As an acolyte, you command the respect of those who share your faith.';

        CharacterTrait::create([
            'reference_type' => Background::class,
            'reference_id' => $background->id,
            'name' => 'Feature: Shelter of the Faithful',
            'category' => 'feature',
            'description' => $featureDescription,
        ]);

        $this->assertEquals($featureDescription, $background->fresh()->feature_description);
    }

    #[Test]
    public function feature_name_returns_null_when_no_feature_trait()
    {
        $background = Background::create(['slug' => 'test', 'name' => 'Test']);

        // Only add a description trait, not a feature trait
        CharacterTrait::create([
            'reference_type' => Background::class,
            'reference_id' => $background->id,
            'name' => 'Description',
            'category' => null,
            'description' => 'Some description',
        ]);

        $this->assertNull($background->fresh()->feature_name);
        $this->assertNull($background->fresh()->feature_description);
    }

    #[Test]
    public function feature_name_handles_feature_without_prefix()
    {
        $background = Background::create(['slug' => 'test', 'name' => 'Test']);

        // Some features might not have the "Feature: " prefix
        CharacterTrait::create([
            'reference_type' => Background::class,
            'reference_id' => $background->id,
            'name' => 'Position of Privilege',
            'category' => 'feature',
            'description' => 'Thanks to your noble birth...',
        ]);

        $this->assertEquals('Position of Privilege', $background->fresh()->feature_name);
    }
}
