<?php

namespace Tests\Feature\Requests;

use App\Http\Requests\ItemIndexRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class ItemIndexRequestTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function it_validates_min_strength_range()
    {
        $request = new ItemIndexRequest;

        // Valid strength (within 1-30 range)
        $validator = Validator::make(
            ['min_strength' => 15],
            $request->rules()
        );
        $this->assertFalse($validator->fails());

        // Invalid strength (above range)
        $validator = Validator::make(
            ['min_strength' => 50],
            $request->rules()
        );
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('min_strength', $validator->errors()->toArray());

        // Invalid strength (below range)
        $validator = Validator::make(
            ['min_strength' => 0],
            $request->rules()
        );
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('min_strength', $validator->errors()->toArray());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_validates_has_prerequisites_boolean()
    {
        $request = new ItemIndexRequest;

        // Valid boolean values
        $validator = Validator::make(
            ['has_prerequisites' => true],
            $request->rules()
        );
        $this->assertFalse($validator->fails());

        $validator = Validator::make(
            ['has_prerequisites' => false],
            $request->rules()
        );
        $this->assertFalse($validator->fails());

        $validator = Validator::make(
            ['has_prerequisites' => 1],
            $request->rules()
        );
        $this->assertFalse($validator->fails());

        $validator = Validator::make(
            ['has_prerequisites' => 0],
            $request->rules()
        );
        $this->assertFalse($validator->fails());

        // Invalid boolean values
        $validator = Validator::make(
            ['has_prerequisites' => 'invalid'],
            $request->rules()
        );
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('has_prerequisites', $validator->errors()->toArray());

        $validator = Validator::make(
            ['has_prerequisites' => 2],
            $request->rules()
        );
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('has_prerequisites', $validator->errors()->toArray());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_validates_per_page_limit()
    {
        $request = new ItemIndexRequest;

        // Valid per_page
        $validator = Validator::make(
            ['per_page' => 50],
            $request->rules()
        );
        $this->assertFalse($validator->fails());

        // Per_page exceeds maximum
        $validator = Validator::make(
            ['per_page' => 150],
            $request->rules()
        );
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('per_page', $validator->errors()->toArray());

        // Per_page below minimum
        $validator = Validator::make(
            ['per_page' => 0],
            $request->rules()
        );
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('per_page', $validator->errors()->toArray());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_whitelists_sortable_columns()
    {
        $request = new ItemIndexRequest;

        // Valid sortable columns
        $validColumns = ['name', 'type', 'rarity', 'created_at', 'updated_at'];

        foreach ($validColumns as $column) {
            $validator = Validator::make(
                ['sort_by' => $column],
                $request->rules()
            );
            $this->assertFalse($validator->fails(), "Column '{$column}' should be sortable");
        }

        // Invalid column
        $validator = Validator::make(
            ['sort_by' => 'invalid_column'],
            $request->rules()
        );
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('sort_by', $validator->errors()->toArray());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_accepts_valid_sort_direction()
    {
        $request = new ItemIndexRequest;

        // Valid sort directions
        $validator = Validator::make(
            ['sort_direction' => 'asc'],
            $request->rules()
        );
        $this->assertFalse($validator->fails());

        $validator = Validator::make(
            ['sort_direction' => 'desc'],
            $request->rules()
        );
        $this->assertFalse($validator->fails());

        // Invalid sort direction
        $validator = Validator::make(
            ['sort_direction' => 'invalid'],
            $request->rules()
        );
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('sort_direction', $validator->errors()->toArray());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_accepts_valid_search_parameter()
    {
        $request = new ItemIndexRequest;

        // Valid search string
        $validator = Validator::make(
            ['search' => 'Longsword'],
            $request->rules()
        );
        $this->assertFalse($validator->fails());

        // Search string too long
        $validator = Validator::make(
            ['search' => str_repeat('a', 256)],
            $request->rules()
        );
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('search', $validator->errors()->toArray());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_validates_min_strength_must_be_integer()
    {
        $request = new ItemIndexRequest;

        // Valid integer
        $validator = Validator::make(
            ['min_strength' => 15],
            $request->rules()
        );
        $this->assertFalse($validator->fails());

        // Invalid string
        $validator = Validator::make(
            ['min_strength' => 'fifteen'],
            $request->rules()
        );
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('min_strength', $validator->errors()->toArray());
    }
}
