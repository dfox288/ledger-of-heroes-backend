<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\OptionalFeatureType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests for OptionalFeatureTypeController.
 *
 * @see \App\Http\Controllers\Api\OptionalFeatureTypeController
 */
#[Group('feature-db')]
class OptionalFeatureTypeApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_can_list_all_optional_feature_types(): void
    {
        $response = $this->getJson('/api/v1/lookups/optional-feature-types');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'value',
                        'label',
                        'default_class',
                        'default_subclass',
                    ],
                ],
            ]);

        // Should return all 8 enum cases
        $this->assertCount(8, $response->json('data'));
    }

    #[Test]
    public function it_returns_all_expected_optional_feature_types(): void
    {
        $response = $this->getJson('/api/v1/lookups/optional-feature-types');

        $response->assertOk();

        $values = collect($response->json('data'))->pluck('value')->toArray();

        $expectedValues = [
            'eldritch_invocation',
            'elemental_discipline',
            'maneuver',
            'metamagic',
            'fighting_style',
            'artificer_infusion',
            'rune',
            'arcane_shot',
        ];

        foreach ($expectedValues as $expected) {
            $this->assertContains($expected, $values, "Missing expected type: {$expected}");
        }
    }

    #[Test]
    public function it_returns_correct_labels_for_each_type(): void
    {
        $response = $this->getJson('/api/v1/lookups/optional-feature-types');

        $response->assertOk();

        $data = collect($response->json('data'));

        $expectedLabels = [
            'eldritch_invocation' => 'Eldritch Invocation',
            'elemental_discipline' => 'Elemental Discipline',
            'maneuver' => 'Maneuver',
            'metamagic' => 'Metamagic',
            'fighting_style' => 'Fighting Style',
            'artificer_infusion' => 'Artificer Infusion',
            'rune' => 'Rune',
            'arcane_shot' => 'Arcane Shot',
        ];

        foreach ($expectedLabels as $value => $expectedLabel) {
            $item = $data->firstWhere('value', $value);
            $this->assertNotNull($item, "Type {$value} not found");
            $this->assertEquals($expectedLabel, $item['label'], "Label mismatch for {$value}");
        }
    }

    #[Test]
    public function it_returns_correct_default_class_for_class_specific_features(): void
    {
        $response = $this->getJson('/api/v1/lookups/optional-feature-types');

        $response->assertOk();

        $data = collect($response->json('data'));

        // Features with specific default classes
        $classSpecific = [
            'eldritch_invocation' => 'Warlock',
            'elemental_discipline' => 'Monk',
            'maneuver' => 'Fighter',
            'metamagic' => 'Sorcerer',
            'artificer_infusion' => 'Artificer',
            'rune' => 'Fighter',
            'arcane_shot' => 'Fighter',
        ];

        foreach ($classSpecific as $value => $expectedClass) {
            $item = $data->firstWhere('value', $value);
            $this->assertEquals($expectedClass, $item['default_class'], "Default class mismatch for {$value}");
        }
    }

    #[Test]
    public function it_returns_null_default_class_for_multi_class_features(): void
    {
        $response = $this->getJson('/api/v1/lookups/optional-feature-types');

        $response->assertOk();

        $fightingStyle = collect($response->json('data'))->firstWhere('value', 'fighting_style');

        $this->assertNotNull($fightingStyle);
        $this->assertNull($fightingStyle['default_class'], 'Fighting Style should have null default_class (multiple classes)');
    }

    #[Test]
    public function it_returns_correct_default_subclass_for_subclass_specific_features(): void
    {
        $response = $this->getJson('/api/v1/lookups/optional-feature-types');

        $response->assertOk();

        $data = collect($response->json('data'));

        // Features with specific default subclasses
        $subclassSpecific = [
            'elemental_discipline' => 'Way of the Four Elements',
            'maneuver' => 'Battle Master',
            'rune' => 'Rune Knight',
            'arcane_shot' => 'Arcane Archer',
        ];

        foreach ($subclassSpecific as $value => $expectedSubclass) {
            $item = $data->firstWhere('value', $value);
            $this->assertEquals($expectedSubclass, $item['default_subclass'], "Default subclass mismatch for {$value}");
        }
    }

    #[Test]
    public function it_returns_null_default_subclass_for_class_level_features(): void
    {
        $response = $this->getJson('/api/v1/lookups/optional-feature-types');

        $response->assertOk();

        $data = collect($response->json('data'));

        // Features that are class-level (not subclass-specific)
        $classLevel = ['eldritch_invocation', 'metamagic', 'fighting_style', 'artificer_infusion'];

        foreach ($classLevel as $value) {
            $item = $data->firstWhere('value', $value);
            $this->assertNull($item['default_subclass'], "{$value} should have null default_subclass");
        }
    }

    #[Test]
    public function it_returns_consistent_count_matching_enum(): void
    {
        $response = $this->getJson('/api/v1/lookups/optional-feature-types');

        $response->assertOk();

        $enumCount = count(OptionalFeatureType::cases());
        $responseCount = count($response->json('data'));

        $this->assertEquals($enumCount, $responseCount, 'Response count should match enum cases');
    }
}
