<?php

namespace Tests\Unit\Services;

use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\CharacterClassPivot;
use App\Services\CharacterClassAssignmentService;
use App\Services\CharacterFeatureService;
use App\Services\CharacterLanguageService;
use App\Services\CharacterProficiencyService;
use App\Services\EquipmentManagerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CharacterClassAssignmentServiceTest extends TestCase
{
    use RefreshDatabase;

    private CharacterClassAssignmentService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new CharacterClassAssignmentService(
            $this->app->make(EquipmentManagerService::class),
            $this->app->make(CharacterProficiencyService::class),
            $this->app->make(CharacterLanguageService::class),
            $this->app->make(CharacterFeatureService::class),
        );
    }

    /** @test */
    public function it_assigns_first_class_as_primary(): void
    {
        $character = Character::factory()->create();
        $class = CharacterClass::factory()->create();

        $isPrimary = $this->service->assignClass($character, $class->slug);

        expect($isPrimary)->toBeTrue();
        expect($character->characterClasses()->count())->toBe(1);
        expect($character->characterClasses()->first()->is_primary)->toBeTrue();
    }

    /** @test */
    public function it_assigns_second_class_as_non_primary(): void
    {
        $character = Character::factory()->create();
        $class1 = CharacterClass::factory()->create();
        $class2 = CharacterClass::factory()->create();

        $this->service->assignClass($character, $class1->slug);
        $isPrimary = $this->service->assignClass($character, $class2->slug);

        expect($isPrimary)->toBeFalse();
        expect($character->characterClasses()->count())->toBe(2);
    }

    /** @test */
    public function it_does_not_duplicate_existing_class(): void
    {
        $character = Character::factory()->create();
        $class = CharacterClass::factory()->create();

        $this->service->assignClass($character, $class->slug);
        $result = $this->service->assignClass($character, $class->slug);

        expect($result)->toBeFalse();
        expect($character->characterClasses()->count())->toBe(1);
    }

    /** @test */
    public function it_updates_primary_class_level(): void
    {
        $character = Character::factory()->create();
        $class = CharacterClass::factory()->create();

        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_slug' => $class->slug,
            'level' => 1,
            'is_primary' => true,
            'order' => 1,
        ]);

        $result = $this->service->updatePrimaryClassLevel($character, 5);

        expect($result)->toBeTrue();
        expect($character->characterClasses()->first()->fresh()->level)->toBe(5);
    }

    /** @test */
    public function it_returns_false_when_no_primary_class_for_level_update(): void
    {
        $character = Character::factory()->create();

        $result = $this->service->updatePrimaryClassLevel($character, 5);

        expect($result)->toBeFalse();
    }
}
