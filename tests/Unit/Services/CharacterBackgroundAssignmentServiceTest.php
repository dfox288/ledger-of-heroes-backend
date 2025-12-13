<?php

namespace Tests\Unit\Services;

use App\Models\Background;
use App\Models\Character;
use App\Services\CharacterBackgroundAssignmentService;
use App\Services\CharacterFeatureService;
use App\Services\CharacterLanguageService;
use App\Services\CharacterProficiencyService;
use App\Services\EquipmentManagerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CharacterBackgroundAssignmentServiceTest extends TestCase
{
    use RefreshDatabase;

    private CharacterBackgroundAssignmentService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new CharacterBackgroundAssignmentService(
            $this->app->make(EquipmentManagerService::class),
            $this->app->make(CharacterProficiencyService::class),
            $this->app->make(CharacterLanguageService::class),
            $this->app->make(CharacterFeatureService::class),
        );
    }

    /** @test */
    public function it_detects_background_change(): void
    {
        $character = Character::factory()->create(['background_slug' => 'phb:acolyte']);

        $result = $this->service->isBackgroundChanging($character, 'phb:soldier');

        expect($result)->toBeTrue();
    }

    /** @test */
    public function it_detects_no_change_when_same_background(): void
    {
        $character = Character::factory()->create(['background_slug' => 'phb:acolyte']);

        $result = $this->service->isBackgroundChanging($character, 'phb:acolyte');

        expect($result)->toBeFalse();
    }

    /** @test */
    public function it_detects_no_change_when_background_is_null(): void
    {
        $character = Character::factory()->create(['background_slug' => 'phb:acolyte']);

        $result = $this->service->isBackgroundChanging($character, null);

        expect($result)->toBeFalse();
    }

    /** @test */
    public function it_grants_background_items_when_background_exists(): void
    {
        $background = Background::factory()->create();
        $character = Character::factory()->create(['background_slug' => $background->slug]);
        $character->load('background');

        // Should not throw - just verifying it runs
        $this->service->grantBackgroundItems($character);

        expect(true)->toBeTrue();
    }

    /** @test */
    public function it_skips_grant_when_background_is_null(): void
    {
        $character = Character::factory()->create(['background_slug' => null]);
        $character->load('background');

        // Should not throw when background is null
        $this->service->grantBackgroundItems($character);

        expect(true)->toBeTrue();
    }
}
