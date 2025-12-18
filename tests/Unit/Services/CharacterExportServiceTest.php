<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Character;
use App\Models\CharacterClassPivot;
use App\Services\CharacterExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CharacterExportServiceTest extends TestCase
{
    use RefreshDatabase;

    private CharacterExportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CharacterExportService::class);
    }

    public function test_exports_subclass_choices_for_classes_with_variant_choices(): void
    {
        $character = Character::factory()->create();

        // Create the character class pivot with subclass_choices
        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => 'test:barbarian',
            'subclass_slug' => 'test:barbarian-path-of-the-totem-warrior',
            'level' => 6,
            'is_primary' => true,
            'order' => 1,
            'subclass_choices' => ['totem_spirit' => 'bear', 'totem_aspect' => 'eagle'],
        ]);

        $exported = $this->service->export($character);

        $this->assertCount(1, $exported['character']['classes']);
        $this->assertEquals(
            ['totem_spirit' => 'bear', 'totem_aspect' => 'eagle'],
            $exported['character']['classes'][0]['subclass_choices']
        );
    }

    public function test_exports_null_subclass_choices_when_none_exist(): void
    {
        $character = Character::factory()->create();

        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => 'test:wizard',
            'subclass_slug' => null,
            'level' => 1,
            'is_primary' => true,
            'order' => 1,
            'subclass_choices' => null,
        ]);

        $exported = $this->service->export($character);

        $this->assertCount(1, $exported['character']['classes']);
        $this->assertNull($exported['character']['classes'][0]['subclass_choices']);
    }
}
