<?php

namespace Tests\Unit\Services;

use App\Models\Character;
use App\Models\Race;
use App\Services\CharacterFeatureService;
use App\Services\CharacterLanguageService;
use App\Services\CharacterProficiencyService;
use App\Services\CharacterRaceAssignmentService;
use App\Services\HitPointService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CharacterRaceAssignmentServiceTest extends TestCase
{
    use RefreshDatabase;

    private CharacterRaceAssignmentService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new CharacterRaceAssignmentService(
            $this->app->make(HitPointService::class),
            $this->app->make(CharacterProficiencyService::class),
            $this->app->make(CharacterLanguageService::class),
            $this->app->make(CharacterFeatureService::class),
        );
    }

    /** @test */
    public function it_detects_race_change(): void
    {
        $character = Character::factory()->create(['race_slug' => 'phb:elf']);

        $result = $this->service->isRaceChanging($character, ['race_slug' => 'phb:dwarf']);

        expect($result)->toBeTrue();
    }

    /** @test */
    public function it_detects_no_change_when_same_race(): void
    {
        $character = Character::factory()->create(['race_slug' => 'phb:elf']);

        $result = $this->service->isRaceChanging($character, ['race_slug' => 'phb:elf']);

        expect($result)->toBeFalse();
    }

    /** @test */
    public function it_detects_no_change_when_race_not_in_validated(): void
    {
        $character = Character::factory()->create(['race_slug' => 'phb:elf']);

        $result = $this->service->isRaceChanging($character, ['name' => 'New Name']);

        expect($result)->toBeFalse();
    }

    /** @test */
    public function it_grants_race_items_when_race_exists(): void
    {
        $race = Race::factory()->create();
        $character = Character::factory()->create(['race_slug' => $race->slug]);
        $character->load('race');

        // Should not throw - just verifying it runs
        $this->service->grantRaceItems($character);

        expect(true)->toBeTrue();
    }

    /** @test */
    public function it_skips_grant_when_race_is_null(): void
    {
        $character = Character::factory()->create(['race_slug' => null]);
        $character->load('race');

        // Should not throw when race is null
        $this->service->grantRaceItems($character);

        expect(true)->toBeTrue();
    }
}
