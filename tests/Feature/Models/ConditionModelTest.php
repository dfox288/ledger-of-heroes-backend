<?php

namespace Tests\Feature\Models;

use App\Models\Condition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

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
}
