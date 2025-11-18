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
            'name' => 'Charmed',
            'slug' => 'charmed',
            'description' => 'A charmed creature cannot attack the charmer.',
        ]);

        $this->assertDatabaseHas('conditions', [
            'name' => 'Charmed',
            'slug' => 'charmed',
        ]);

        $this->assertEquals('Charmed', $condition->name);
        $this->assertEquals('charmed', $condition->slug);
    }

    #[Test]
    public function it_does_not_have_timestamps(): void
    {
        $condition = Condition::create([
            'name' => 'Frightened',
            'slug' => 'frightened',
            'description' => 'A frightened creature has disadvantage on ability checks.',
        ]);

        $this->assertNull($condition->created_at);
        $this->assertNull($condition->updated_at);
    }

    #[Test]
    public function it_enforces_unique_slug(): void
    {
        Condition::create([
            'name' => 'Poisoned',
            'slug' => 'poisoned',
            'description' => 'A poisoned creature has disadvantage on attack rolls.',
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        Condition::create([
            'name' => 'Poisoned (Duplicate)',
            'slug' => 'poisoned', // Same slug
            'description' => 'Duplicate condition.',
        ]);
    }
}
