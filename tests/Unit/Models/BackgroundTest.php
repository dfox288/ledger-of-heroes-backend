<?php

namespace Tests\Unit\Models;

use App\Models\Background;
use App\Models\CharacterTrait;
use App\Models\EntityItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BackgroundTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_has_many_equipment(): void
    {
        $background = Background::factory()->create();
        EntityItem::factory()->count(3)->create([
            'reference_type' => Background::class,
            'reference_id' => $background->id,
        ]);

        $this->assertCount(3, $background->equipment);
        $this->assertInstanceOf(EntityItem::class, $background->equipment->first());
    }

    #[Test]
    public function feature_name_accessor_returns_feature_trait_name_without_prefix(): void
    {
        $background = Background::factory()->create();
        CharacterTrait::factory()->create([
            'reference_type' => Background::class,
            'reference_id' => $background->id,
            'category' => 'feature',
            'name' => 'Feature: Shelter of the Faithful',
        ]);

        $this->assertEquals('Shelter of the Faithful', $background->feature_name);
    }

    #[Test]
    public function feature_name_accessor_returns_name_as_is_when_no_prefix(): void
    {
        $background = Background::factory()->create();
        CharacterTrait::factory()->create([
            'reference_type' => Background::class,
            'reference_id' => $background->id,
            'category' => 'feature',
            'name' => 'Shelter of the Faithful',
        ]);

        $this->assertEquals('Shelter of the Faithful', $background->feature_name);
    }

    #[Test]
    public function feature_name_accessor_returns_null_when_no_feature_trait(): void
    {
        $background = Background::factory()->create();
        CharacterTrait::factory()->create([
            'reference_type' => Background::class,
            'reference_id' => $background->id,
            'category' => 'personality',
            'name' => 'I always want to know how things work.',
        ]);

        $this->assertNull($background->feature_name);
    }

    #[Test]
    public function feature_description_accessor_returns_feature_trait_description(): void
    {
        $background = Background::factory()->create();
        CharacterTrait::factory()->create([
            'reference_type' => Background::class,
            'reference_id' => $background->id,
            'category' => 'feature',
            'name' => 'Feature: Shelter of the Faithful',
            'description' => 'As an acolyte, you command the respect of those who share your faith.',
        ]);

        $this->assertEquals(
            'As an acolyte, you command the respect of those who share your faith.',
            $background->feature_description
        );
    }

    #[Test]
    public function feature_description_accessor_returns_null_when_no_feature_trait(): void
    {
        $background = Background::factory()->create();

        $this->assertNull($background->feature_description);
    }

    #[Test]
    public function feature_name_handles_multiple_traits_correctly(): void
    {
        $background = Background::factory()->create();

        // Create non-feature traits
        CharacterTrait::factory()->create([
            'reference_type' => Background::class,
            'reference_id' => $background->id,
            'category' => 'personality',
            'name' => 'Personality Trait',
        ]);

        // Create feature trait
        CharacterTrait::factory()->create([
            'reference_type' => Background::class,
            'reference_id' => $background->id,
            'category' => 'feature',
            'name' => 'Feature: By Popular Demand',
        ]);

        $this->assertEquals('By Popular Demand', $background->feature_name);
    }

    #[Test]
    public function feature_name_strips_various_prefix_formats(): void
    {
        $background = Background::factory()->create();
        CharacterTrait::factory()->create([
            'reference_type' => Background::class,
            'reference_id' => $background->id,
            'category' => 'feature',
            'name' => 'Feature: Discovery',
        ]);

        $name = $background->feature_name;

        $this->assertEquals('Discovery', $name);
        $this->assertStringNotContainsString('Feature:', $name);
    }

    #[Test]
    public function it_loads_traits_relationship_for_feature_accessors(): void
    {
        $background = Background::factory()->create();
        CharacterTrait::factory()->create([
            'reference_type' => Background::class,
            'reference_id' => $background->id,
            'category' => 'feature',
            'name' => 'Feature: False Identity',
            'description' => 'You have created a second identity.',
        ]);

        // Access both accessors
        $name = $background->feature_name;
        $description = $background->feature_description;

        $this->assertEquals('False Identity', $name);
        $this->assertEquals('You have created a second identity.', $description);
    }
}
