<?php

namespace Tests\Feature\Requests;

use App\Http\Requests\ItemShowRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('feature-db')]
class ItemShowRequestTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function it_whitelists_includable_relationships()
    {
        $request = new ItemShowRequest;

        // Valid relationships
        $validRelationships = [
            'sources',
            'sources.source',
            'modifiers',
            'abilities',
            'prerequisites',
            'prerequisites.prerequisite',
        ];

        foreach ($validRelationships as $relationship) {
            $validator = Validator::make(
                ['include' => [$relationship]],
                $request->rules()
            );
            $this->assertFalse($validator->fails(), "Relationship '{$relationship}' should be includable");
        }

        // Invalid relationship
        $validator = Validator::make(
            ['include' => ['invalid_relationship']],
            $request->rules()
        );
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('include.0', $validator->errors()->toArray());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_whitelists_selectable_fields()
    {
        $request = new ItemShowRequest;

        // Valid fields
        $validFields = [
            'id',
            'name',
            'slug',
            'type',
            'rarity',
            'description',
            'magic',
            'attunement',
            'strength_requirement',
            'created_at',
            'updated_at',
        ];

        foreach ($validFields as $field) {
            $validator = Validator::make(
                ['fields' => [$field]],
                $request->rules()
            );
            $this->assertFalse($validator->fails(), "Field '{$field}' should be selectable");
        }

        // Invalid field
        $validator = Validator::make(
            ['fields' => ['invalid_field']],
            $request->rules()
        );
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('fields.0', $validator->errors()->toArray());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_accepts_multiple_includes()
    {
        $request = new ItemShowRequest;

        // Multiple valid relationships
        $validator = Validator::make(
            ['include' => ['sources', 'modifiers', 'abilities']],
            $request->rules()
        );
        $this->assertFalse($validator->fails());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_accepts_multiple_fields()
    {
        $request = new ItemShowRequest;

        // Multiple valid fields
        $validator = Validator::make(
            ['fields' => ['id', 'name', 'slug', 'rarity']],
            $request->rules()
        );
        $this->assertFalse($validator->fails());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_validates_include_must_be_array()
    {
        $request = new ItemShowRequest;

        // Invalid - string instead of array
        $validator = Validator::make(
            ['include' => 'sources'],
            $request->rules()
        );
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('include', $validator->errors()->toArray());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_validates_fields_must_be_array()
    {
        $request = new ItemShowRequest;

        // Invalid - string instead of array
        $validator = Validator::make(
            ['fields' => 'name'],
            $request->rules()
        );
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('fields', $validator->errors()->toArray());
    }
}
