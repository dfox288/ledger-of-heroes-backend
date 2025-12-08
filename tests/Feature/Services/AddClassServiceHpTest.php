<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Models\Character;
use App\Models\CharacterClass;
use App\Services\AddClassService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AddClassServiceHpTest extends TestCase
{
    use RefreshDatabase;

    private AddClassService $service;

    private CharacterClass $fighter;

    private CharacterClass $wizard;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(AddClassService::class);

        $this->fighter = CharacterClass::factory()->create([
            'slug' => 'fighter',
            'name' => 'Fighter',
            'hit_die' => 10,
            'parent_class_id' => null,
        ]);

        $this->wizard = CharacterClass::factory()->create([
            'slug' => 'wizard',
            'name' => 'Wizard',
            'hit_die' => 6,
            'parent_class_id' => null,
        ]);
    }

    #[Test]
    public function it_auto_initializes_hp_when_first_class_is_added(): void
    {
        $character = Character::factory()->create([
            'constitution' => 14, // +2 modifier
            'max_hit_points' => null,
            'current_hit_points' => null,
            'hp_levels_resolved' => null,
            'hp_calculation_method' => 'calculated',
        ]);

        $this->service->addClass($character, $this->fighter);

        $character->refresh();

        $this->assertEquals(12, $character->max_hit_points); // 10 + 2
        $this->assertEquals(12, $character->current_hit_points);
        $this->assertEquals([1], $character->hp_levels_resolved);
    }

    #[Test]
    public function it_auto_initializes_hp_for_wizard_with_different_hit_die(): void
    {
        $character = Character::factory()->create([
            'constitution' => 12, // +1 modifier
            'max_hit_points' => null,
            'current_hit_points' => null,
            'hp_levels_resolved' => null,
            'hp_calculation_method' => 'calculated',
        ]);

        $this->service->addClass($character, $this->wizard);

        $character->refresh();

        $this->assertEquals(7, $character->max_hit_points); // 6 + 1
        $this->assertEquals(7, $character->current_hit_points);
        $this->assertEquals([1], $character->hp_levels_resolved);
    }

    #[Test]
    public function it_does_not_change_hp_when_second_class_is_added(): void
    {
        $character = Character::factory()
            ->withClass($this->fighter, 5)
            ->create([
                'constitution' => 14,
                'max_hit_points' => 52,
                'current_hit_points' => 52,
                'hp_levels_resolved' => [1, 2, 3, 4, 5],
                'hp_calculation_method' => 'calculated',
            ]);

        $this->service->addClass($character, $this->wizard);

        $character->refresh();

        $this->assertEquals(52, $character->max_hit_points); // Unchanged
        $this->assertEquals(52, $character->current_hit_points);
        // hp_levels_resolved should not change for multiclass
        $this->assertEquals([1, 2, 3, 4, 5], $character->hp_levels_resolved);
    }

    #[Test]
    public function it_does_not_auto_initialize_hp_for_manual_characters(): void
    {
        $character = Character::factory()->create([
            'constitution' => 14,
            'max_hit_points' => 20, // Manually set
            'current_hit_points' => 20,
            'hp_levels_resolved' => null,
            'hp_calculation_method' => 'manual',
        ]);

        $this->service->addClass($character, $this->fighter);

        $character->refresh();

        $this->assertEquals(20, $character->max_hit_points); // Unchanged
        $this->assertEquals(20, $character->current_hit_points);
    }

    #[Test]
    public function it_handles_low_constitution_with_minimum_1_hp(): void
    {
        $character = Character::factory()->create([
            'constitution' => 3, // -4 modifier
            'max_hit_points' => null,
            'current_hit_points' => null,
            'hp_levels_resolved' => null,
            'hp_calculation_method' => 'calculated',
        ]);

        $this->service->addClass($character, $this->wizard);

        $character->refresh();

        // 6 - 4 = 2, minimum is 1 but 2 > 1 so result is 2
        $this->assertEquals(2, $character->max_hit_points);
        $this->assertEquals(2, $character->current_hit_points);
        $this->assertEquals([1], $character->hp_levels_resolved);
    }
}
