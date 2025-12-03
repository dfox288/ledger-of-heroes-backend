<?php

namespace Tests\Feature\Models;

use App\Models\Condition;
use App\Models\EntityCondition;
use App\Models\Feat;
use App\Models\Race;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('feature-db')]
class ConditionModelTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_can_create_a_condition(): void
    {
        $condition = Condition::create([
            'name' => 'Test Condition',
            'slug' => 'test-condition',
            'description' => 'A test condition for unit testing.',
        ]);

        $this->assertDatabaseHas('conditions', [
            'name' => 'Test Condition',
            'slug' => 'test-condition',
        ]);

        $this->assertEquals('Test Condition', $condition->name);
        $this->assertEquals('test-condition', $condition->slug);
    }

    #[Test]
    public function it_does_not_have_timestamps(): void
    {
        $condition = Condition::create([
            'name' => 'Test Timestamps',
            'slug' => 'test-timestamps',
            'description' => 'Testing that timestamps are not created.',
        ]);

        $this->assertNull($condition->created_at);
        $this->assertNull($condition->updated_at);
    }

    #[Test]
    public function it_enforces_unique_slug(): void
    {
        Condition::create([
            'name' => 'Unique Test',
            'slug' => 'unique-test',
            'description' => 'First unique test condition.',
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        Condition::create([
            'name' => 'Unique Test (Duplicate)',
            'slug' => 'unique-test', // Same slug
            'description' => 'Duplicate condition.',
        ]);
    }

    #[Test]
    public function it_has_feats_inverse_relationship(): void
    {
        $condition = Condition::create([
            'name' => 'Test Condition For Feats',
            'slug' => 'test-condition-feats-'.uniqid(),
            'description' => 'A test condition for feats relationship.',
        ]);

        $feat = Feat::factory()->create(['name' => 'Test Feat With Condition']);

        EntityCondition::create([
            'reference_type' => Feat::class,
            'reference_id' => $feat->id,
            'condition_id' => $condition->id,
            'effect_type' => 'advantage',
            'description' => 'Advantage on saves against this condition',
        ]);

        $this->assertCount(1, $condition->feats);
        $this->assertEquals('Test Feat With Condition', $condition->feats->first()->name);
        $this->assertEquals('advantage', $condition->feats->first()->pivot->effect_type);
    }

    #[Test]
    public function it_has_races_inverse_relationship(): void
    {
        $condition = Condition::create([
            'name' => 'Test Condition For Races',
            'slug' => 'test-condition-races-'.uniqid(),
            'description' => 'A test condition for races relationship.',
        ]);

        $race = Race::factory()->create(['name' => 'Test Race With Condition']);

        EntityCondition::create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'condition_id' => $condition->id,
            'effect_type' => 'advantage',
            'description' => 'Advantage on saves against this condition',
        ]);

        $this->assertCount(1, $condition->races);
        $this->assertEquals('Test Race With Condition', $condition->races->first()->name);
        $this->assertEquals('advantage', $condition->races->first()->pivot->effect_type);
    }
}
