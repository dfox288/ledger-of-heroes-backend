<?php

namespace Tests\Feature\Models;

use App\Models\Character;
use App\Models\CharacterCondition;
use App\Models\Condition;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CharacterConditionModelTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_belongs_to_a_character(): void
    {
        $characterCondition = CharacterCondition::factory()->create();

        $this->assertInstanceOf(Character::class, $characterCondition->character);
    }

    #[Test]
    public function it_belongs_to_a_condition(): void
    {
        $characterCondition = CharacterCondition::factory()->create();

        $this->assertInstanceOf(Condition::class, $characterCondition->condition);
    }

    #[Test]
    public function it_can_have_a_level_for_exhaustion(): void
    {
        $exhaustion = Condition::firstOrCreate(
            ['slug' => 'exhaustion'],
            ['name' => 'Exhaustion', 'description' => 'Exhaustion condition']
        );
        $characterCondition = CharacterCondition::factory()->create([
            'condition_id' => $exhaustion->id,
            'level' => 3,
        ]);

        $this->assertEquals(3, $characterCondition->level);
    }

    #[Test]
    public function it_enforces_unique_condition_per_character(): void
    {
        $character = Character::factory()->create();
        $condition = Condition::factory()->create();

        CharacterCondition::factory()->create([
            'character_id' => $character->id,
            'condition_id' => $condition->id,
        ]);

        $this->expectException(QueryException::class);

        CharacterCondition::factory()->create([
            'character_id' => $character->id,
            'condition_id' => $condition->id,
        ]);
    }

    #[Test]
    public function character_has_many_conditions(): void
    {
        $character = Character::factory()->create();
        CharacterCondition::factory()->count(3)->create(['character_id' => $character->id]);

        $this->assertCount(3, $character->conditions);
    }

    #[Test]
    public function it_stores_source_and_duration(): void
    {
        $characterCondition = CharacterCondition::factory()->create([
            'source' => 'Spider bite',
            'duration' => '1 hour',
        ]);

        $this->assertEquals('Spider bite', $characterCondition->source);
        $this->assertEquals('1 hour', $characterCondition->duration);
    }

    #[Test]
    public function it_casts_level_to_integer(): void
    {
        $characterCondition = CharacterCondition::factory()->create([
            'level' => '3',
        ]);

        $this->assertIsInt($characterCondition->level);
        $this->assertEquals(3, $characterCondition->level);
    }

    #[Test]
    public function deleting_character_cascades_to_conditions(): void
    {
        $character = Character::factory()->create();
        CharacterCondition::factory()->count(2)->create(['character_id' => $character->id]);

        $this->assertDatabaseCount('character_conditions', 2);

        $character->delete();

        $this->assertDatabaseCount('character_conditions', 0);
    }
}
