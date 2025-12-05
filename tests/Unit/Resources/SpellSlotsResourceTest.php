<?php

namespace Tests\Unit\Resources;

use App\Http\Resources\SpellSlotsResource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('unit-db')]
class SpellSlotsResourceTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_transforms_old_structure_with_standard_slots(): void
    {
        $data = [
            'standard' => [
                '1' => ['max' => 4, 'used' => 2],
                '2' => ['max' => 3, 'used' => 1],
                '3' => ['max' => 2, 'used' => 0],
            ],
        ];

        $resource = new SpellSlotsResource($data);
        $array = $resource->toArray(request());

        $this->assertArrayHasKey('standard', $array);
        $this->assertIsObject($array['standard']);

        // Convert to array to verify structure
        $standardArray = (array) $array['standard'];
        $this->assertArrayHasKey('1', $standardArray);
        $this->assertArrayHasKey('2', $standardArray);
        $this->assertArrayHasKey('3', $standardArray);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_transforms_old_structure_with_pact_magic(): void
    {
        $data = [
            'pact_magic' => [
                '1' => ['max' => 2, 'used' => 0],
                '2' => ['max' => 2, 'used' => 1],
            ],
        ];

        $resource = new SpellSlotsResource($data);
        $array = $resource->toArray(request());

        $this->assertArrayHasKey('pact_magic', $array);
        $this->assertIsObject($array['pact_magic']);

        $pactArray = (array) $array['pact_magic'];
        $this->assertArrayHasKey('1', $pactArray);
        $this->assertArrayHasKey('2', $pactArray);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_transforms_old_structure_with_both_standard_and_pact_magic(): void
    {
        $data = [
            'standard' => [
                '1' => ['max' => 4, 'used' => 2],
                '2' => ['max' => 3, 'used' => 1],
            ],
            'pact_magic' => [
                '1' => ['max' => 2, 'used' => 0],
            ],
        ];

        $resource = new SpellSlotsResource($data);
        $array = $resource->toArray(request());

        $this->assertArrayHasKey('standard', $array);
        $this->assertArrayHasKey('pact_magic', $array);
        $this->assertIsObject($array['standard']);
        $this->assertIsObject($array['pact_magic']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_transforms_new_consolidated_structure(): void
    {
        $data = [
            'slots' => [
                '1' => ['max' => 4, 'used' => 2],
                '2' => ['max' => 3, 'used' => 1],
                '3' => ['max' => 2, 'used' => 0],
            ],
            'prepared_count' => 5,
            'preparation_limit' => 8,
        ];

        $resource = new SpellSlotsResource($data);
        $array = $resource->toArray(request());

        $this->assertArrayHasKey('slots', $array);
        $this->assertArrayHasKey('prepared_count', $array);
        $this->assertArrayHasKey('preparation_limit', $array);
        $this->assertIsObject($array['slots']);
        $this->assertEquals(5, $array['prepared_count']);
        $this->assertEquals(8, $array['preparation_limit']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_preserves_spell_level_keys_as_strings_in_json(): void
    {
        $data = [
            'slots' => [
                '1' => ['max' => 4, 'used' => 2],
                '2' => ['max' => 3, 'used' => 1],
                '9' => ['max' => 1, 'used' => 0],
            ],
            'prepared_count' => 5,
        ];

        $resource = new SpellSlotsResource($data);
        $array = $resource->toArray(request());

        // Cast to object preserves string keys in JSON
        $this->assertIsObject($array['slots']);
        $slotsArray = (array) $array['slots'];
        $this->assertArrayHasKey('1', $slotsArray);
        $this->assertArrayHasKey('2', $slotsArray);
        $this->assertArrayHasKey('9', $slotsArray);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_empty_slots_array(): void
    {
        $data = [
            'slots' => [],
            'prepared_count' => 0,
            'preparation_limit' => 5,
        ];

        $resource = new SpellSlotsResource($data);
        $array = $resource->toArray(request());

        $this->assertArrayHasKey('slots', $array);
        $this->assertIsObject($array['slots']);
        $this->assertEquals(0, $array['prepared_count']);
        $this->assertEquals(5, $array['preparation_limit']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_null_preparation_limit(): void
    {
        $data = [
            'slots' => [
                '1' => ['max' => 4, 'used' => 2],
            ],
            'prepared_count' => 3,
            'preparation_limit' => null,
        ];

        $resource = new SpellSlotsResource($data);
        $array = $resource->toArray(request());

        $this->assertArrayHasKey('preparation_limit', $array);
        $this->assertNull($array['preparation_limit']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_does_not_modify_structure_without_slots_key(): void
    {
        $data = [
            'custom_field' => 'value',
            'another_field' => 123,
        ];

        $resource = new SpellSlotsResource($data);
        $array = $resource->toArray(request());

        $this->assertArrayHasKey('custom_field', $array);
        $this->assertArrayHasKey('another_field', $array);
        $this->assertEquals('value', $array['custom_field']);
        $this->assertEquals(123, $array['another_field']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_only_converts_to_object_when_prepared_count_exists(): void
    {
        // Test that old structure with 'slots' key but no prepared_count doesn't get converted
        $data = [
            'slots' => ['some' => 'data'],
        ];

        $resource = new SpellSlotsResource($data);
        $array = $resource->toArray(request());

        // Without prepared_count, it should not convert to object
        $this->assertArrayHasKey('slots', $array);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_standard_slots_without_pact_magic(): void
    {
        $data = [
            'standard' => [
                '1' => ['max' => 4, 'used' => 2],
                '2' => ['max' => 3, 'used' => 1],
            ],
        ];

        $resource = new SpellSlotsResource($data);
        $array = $resource->toArray(request());

        $this->assertArrayHasKey('standard', $array);
        $this->assertArrayNotHasKey('pact_magic', $array);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_pact_magic_without_standard_slots(): void
    {
        $data = [
            'pact_magic' => [
                '5' => ['max' => 2, 'used' => 1],
            ],
        ];

        $resource = new SpellSlotsResource($data);
        $array = $resource->toArray(request());

        $this->assertArrayHasKey('pact_magic', $array);
        $this->assertArrayNotHasKey('standard', $array);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_preserves_nested_slot_data_structure(): void
    {
        $data = [
            'slots' => [
                '1' => ['max' => 4, 'used' => 2, 'available' => 2],
                '2' => ['max' => 3, 'used' => 1, 'available' => 2],
            ],
            'prepared_count' => 5,
        ];

        $resource = new SpellSlotsResource($data);
        $array = $resource->toArray(request());

        $this->assertIsObject($array['slots']);
        $slotsArray = (array) $array['slots'];
        $level1 = (array) $slotsArray['1'];
        $this->assertEquals(4, $level1['max']);
        $this->assertEquals(2, $level1['used']);
        $this->assertEquals(2, $level1['available']);
    }
}
