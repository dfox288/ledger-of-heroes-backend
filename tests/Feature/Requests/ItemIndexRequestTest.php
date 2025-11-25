<?php

namespace Tests\Feature\Requests;

use App\Http\Requests\ItemIndexRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class ItemIndexRequestTest extends TestCase
{
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
    public function it_accepts_valid_q_parameter()
    {
        $request = new ItemIndexRequest;

        // Valid search query
        $validator = Validator::make(
            ['q' => 'Longsword'],
            $request->rules()
        );
        $this->assertFalse($validator->fails());

        // Query string too short
        $validator = Validator::make(
            ['q' => 'a'],
            $request->rules()
        );
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('q', $validator->errors()->toArray());

        // Query string too long
        $validator = Validator::make(
            ['q' => str_repeat('a', 256)],
            $request->rules()
        );
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('q', $validator->errors()->toArray());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_accepts_valid_filter_parameter()
    {
        $request = new ItemIndexRequest;

        // Valid filter expression
        $validator = Validator::make(
            ['filter' => 'rarity = "legendary"'],
            $request->rules()
        );
        $this->assertFalse($validator->fails());

        // Filter string too long
        $validator = Validator::make(
            ['filter' => str_repeat('a', 1001)],
            $request->rules()
        );
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('filter', $validator->errors()->toArray());
    }
}
