<?php

namespace Tests\Unit\Services\Importers\Concerns;

use App\Models\Condition;
use App\Models\EntityCondition;
use App\Models\Feat;
use App\Models\Race;
use App\Services\Importers\Concerns\ImportsConditions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('unit-db')]
class ImportsConditionsTest extends TestCase
{
    use ImportsConditions;
    use RefreshDatabase;

    #[Test]
    public function it_imports_conditions_with_condition_lookup()
    {
        $race = Race::factory()->create();
        $poisoned = Condition::firstOrCreate(
            ['slug' => 'core:poisoned'],
            ['name' => 'Poisoned', 'description' => 'Disadvantage on attack rolls and ability checks.']
        );

        $this->importEntityConditions($race, [
            [
                'condition_name' => 'poisoned',
                'effect_type' => 'immunity',
            ],
        ]);

        $condition = $race->fresh()->conditions->first();
        $this->assertNotNull($condition);
        $this->assertEquals($poisoned->id, $condition->condition_id);
        $this->assertEquals('immunity', $condition->effect_type);
    }

    #[Test]
    public function it_imports_conditions_with_description_only()
    {
        $race = Race::factory()->create();

        $conditionsData = [
            [
                'effect_type' => 'advantage',
                'description' => 'Advantage on saving throws against being charmed',
            ],
        ];

        $this->importEntityConditions($race, $conditionsData);

        $condition = $race->conditions->first();
        $this->assertNull($condition->condition_id);
        $this->assertEquals('advantage', $condition->effect_type);
        $this->assertEquals('Advantage on saving throws against being charmed', $condition->description);
    }

    #[Test]
    public function it_imports_multiple_conditions()
    {
        $race = Race::factory()->create();
        $poisoned = Condition::firstOrCreate(
            ['slug' => 'core:poisoned'],
            ['name' => 'Poisoned', 'description' => '']
        );
        $charmed = Condition::firstOrCreate(
            ['slug' => 'core:charmed'],
            ['name' => 'Charmed', 'description' => '']
        );

        $this->importEntityConditions($race, [
            ['condition_name' => 'poisoned', 'effect_type' => 'immunity'],
            ['condition_name' => 'charmed', 'effect_type' => 'advantage'],
        ]);

        $race->refresh();
        $this->assertCount(2, $race->conditions);
        $conditionIds = $race->conditions->pluck('condition_id')->all();
        $this->assertContains($poisoned->id, $conditionIds);
        $this->assertContains($charmed->id, $conditionIds);
    }

    #[Test]
    public function it_clears_existing_conditions_before_import()
    {
        $race = Race::factory()->create();

        // Create initial conditions
        EntityCondition::create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'condition_id' => null,
            'effect_type' => 'immunity',
            'description' => 'Old condition',
        ]);

        $this->assertCount(1, $race->fresh()->conditions);

        // Import new conditions (should clear old ones)
        $conditionsData = [
            [
                'effect_type' => 'advantage',
                'description' => 'New condition',
            ],
        ];

        $this->importEntityConditions($race, $conditionsData);

        $race->refresh();
        $this->assertCount(1, $race->conditions);
        $this->assertEquals('New condition', $race->conditions->first()->description);
    }

    #[Test]
    public function it_handles_condition_name_with_slug_normalization()
    {
        $race = Race::factory()->create();
        $frightened = Condition::firstOrCreate(
            ['slug' => 'core:frightened'],
            ['name' => 'Frightened', 'description' => '']
        );

        // Pass condition_name with capitalization; trait normalizes via Str::slug
        // and resolves to the canonical 'core:frightened' slug.
        $this->importEntityConditions($race, [
            [
                'condition_name' => 'Frightened',
                'effect_type' => 'immunity',
            ],
        ]);

        $condition = $race->fresh()->conditions->first();
        $this->assertNotNull($condition);
        $this->assertEquals($frightened->id, $condition->condition_id);
    }

    #[Test]
    public function it_skips_conditions_when_lookup_fails()
    {
        $race = Race::factory()->create();

        $conditionsData = [
            [
                'condition_name' => 'NonExistentCondition',
                'effect_type' => 'immunity',
            ],
        ];

        $this->importEntityConditions($race, $conditionsData);

        $this->assertCount(0, $race->conditions);
    }

    #[Test]
    public function it_handles_empty_conditions_array()
    {
        $race = Race::factory()->create();

        // Create initial condition
        EntityCondition::create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'condition_id' => null,
            'effect_type' => 'immunity',
            'description' => 'Test',
        ]);

        $this->assertCount(1, $race->fresh()->conditions);

        // Import empty array (should clear all)
        $this->importEntityConditions($race, []);

        $this->assertCount(0, $race->fresh()->conditions);
    }

    #[Test]
    public function it_works_with_feats()
    {
        $feat = Feat::factory()->create();

        $conditionsData = [
            [
                'effect_type' => 'advantage',
                'description' => 'Advantage on initiative rolls',
            ],
        ];

        $this->importEntityConditions($feat, $conditionsData);

        $this->assertCount(1, $feat->conditions);
        $this->assertEquals('advantage', $feat->conditions->first()->effect_type);
    }
}
