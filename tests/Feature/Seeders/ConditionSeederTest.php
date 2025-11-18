<?php

namespace Tests\Feature\Seeders;

use App\Models\Condition;
use Database\Seeders\ConditionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ConditionSeederTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_seeds_all_15_dnd_conditions(): void
    {
        $this->seed(ConditionSeeder::class);

        $this->assertEquals(15, Condition::count());
    }

    #[Test]
    public function it_seeds_specific_conditions(): void
    {
        $this->seed(ConditionSeeder::class);

        $expectedConditions = [
            'blinded',
            'charmed',
            'deafened',
            'frightened',
            'grappled',
            'incapacitated',
            'invisible',
            'paralyzed',
            'petrified',
            'poisoned',
            'prone',
            'restrained',
            'stunned',
            'unconscious',
            'exhaustion',
        ];

        foreach ($expectedConditions as $slug) {
            $this->assertDatabaseHas('conditions', ['slug' => $slug]);
        }
    }

    #[Test]
    public function it_creates_conditions_with_descriptions(): void
    {
        $this->seed(ConditionSeeder::class);

        $charmed = Condition::where('slug', 'charmed')->first();

        $this->assertNotNull($charmed);
        $this->assertEquals('Charmed', $charmed->name);
        $this->assertNotEmpty($charmed->description);
        $this->assertStringContainsString('charmed creature', strtolower($charmed->description));
    }

    #[Test]
    public function it_is_idempotent(): void
    {
        $this->seed(ConditionSeeder::class);
        $this->assertEquals(15, Condition::count());

        // Run again - should not create duplicates
        $this->seed(ConditionSeeder::class);
        $this->assertEquals(15, Condition::count());
    }
}
