<?php

namespace Tests\Feature\Api;

use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\CharacterClassPivot;
use App\Models\ClassCounter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('feature-db')]
class CharacterChoiceApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_empty_choices_for_character_with_no_handlers(): void
    {
        $character = Character::factory()->create();

        $response = $this->getJson("/api/v1/characters/{$character->id}/pending-choices");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'choices',
                    'summary' => [
                        'total_pending',
                        'required_pending',
                        'optional_pending',
                        'by_type',
                        'by_source',
                    ],
                ],
            ])
            ->assertJsonPath('data.choices', [])
            ->assertJsonPath('data.summary.total_pending', 0);
    }

    #[Test]
    public function it_returns_404_for_non_existent_character_on_index(): void
    {
        $response = $this->getJson('/api/v1/characters/99999/pending-choices');

        $response->assertNotFound();
    }

    #[Test]
    public function it_accepts_type_query_parameter(): void
    {
        $character = Character::factory()->create();

        $response = $this->getJson("/api/v1/characters/{$character->id}/pending-choices?type=proficiency");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'choices',
                    'summary',
                ],
            ]);
    }

    #[Test]
    public function it_returns_404_for_unknown_choice_type_on_show(): void
    {
        $character = Character::factory()->create();

        $response = $this->getJson("/api/v1/characters/{$character->id}/pending-choices/unknown:type:1:1:group");

        $response->assertNotFound()
            ->assertJsonStructure([
                'message',
                'choice_id',
            ]);
    }

    #[Test]
    public function it_returns_404_for_non_existent_character_on_show(): void
    {
        $response = $this->getJson('/api/v1/characters/99999/pending-choices/proficiency:class:1:1:skill_choice_1');

        $response->assertNotFound();
    }

    #[Test]
    public function it_returns_404_for_unknown_choice_type_on_resolve(): void
    {
        $character = Character::factory()->create();

        $response = $this->postJson(
            "/api/v1/characters/{$character->id}/choices/unknown:type:1:1:group",
            ['selected' => ['option1']]
        );

        $response->assertNotFound()
            ->assertJsonStructure([
                'message',
                'choice_id',
            ]);
    }

    #[Test]
    public function it_returns_404_for_unregistered_choice_type_on_resolve(): void
    {
        $character = Character::factory()->create();

        // With no handlers registered, this will return 404 for unknown type
        $response = $this->postJson(
            "/api/v1/characters/{$character->id}/choices/proficiency:class:1:1:skill",
            ['selected' => ['stealth', 'athletics']]
        );

        // 404 because no proficiency handler is registered yet
        $response->assertNotFound();
    }

    #[Test]
    public function it_returns_404_for_unknown_choice_type_on_undo(): void
    {
        $character = Character::factory()->create();

        $response = $this->deleteJson(
            "/api/v1/characters/{$character->id}/choices/unknown:type:1:1:group"
        );

        $response->assertNotFound()
            ->assertJsonStructure([
                'message',
                'choice_id',
            ]);
    }

    #[Test]
    public function it_returns_404_for_non_existent_character_on_undo(): void
    {
        $response = $this->deleteJson('/api/v1/characters/99999/choices/proficiency:class:1:1:skill');

        $response->assertNotFound();
    }

    #[Test]
    public function it_validates_item_selections_keys_must_be_in_selected(): void
    {
        $character = Character::factory()->create();

        // item_selections key 'z' is not in selected array ['a']
        $response = $this->postJson(
            "/api/v1/characters/{$character->id}/choices/equipment|class|phb:bard|1|choice_1",
            [
                'selected' => ['a'],
                'item_selections' => ['z' => ['phb:drum']],
            ]
        );

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['item_selections.z']);
    }

    #[Test]
    public function it_validates_item_selections_cannot_be_empty_array(): void
    {
        $character = Character::factory()->create();

        // item_selections for 'b' is an empty array
        $response = $this->postJson(
            "/api/v1/characters/{$character->id}/choices/equipment|class|phb:bard|1|choice_1",
            [
                'selected' => ['b'],
                'item_selections' => ['b' => []],
            ]
        );

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['item_selections.b']);
    }

    // =====================
    // Fighting Style Choice Tests (Issue #491)
    // =====================

    #[Test]
    public function it_returns_single_fighting_style_choice_for_fighter(): void
    {
        // Create Fighter class with "Fighting Styles Known" counter at level 1
        $fighter = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'slug' => 'fighter',
        ]);

        ClassCounter::factory()
            ->forClass($fighter)
            ->atLevel(1)
            ->noReset()
            ->create([
                'counter_name' => 'Fighting Styles Known',
                'counter_value' => 1,
            ]);

        // Create character with Fighter class at level 1
        $character = Character::factory()->create();
        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_slug' => $fighter->slug,
            'level' => 1,
            'is_primary' => true,
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/pending-choices");

        $response->assertOk();

        $choices = $response->json('data.choices');

        // Filter to only fighting style related choices
        $fightingStyleChoices = collect($choices)->filter(function ($choice) {
            return $choice['type'] === 'fighting_style'
                || ($choice['type'] === 'optional_feature' && $choice['subtype'] === 'fighting_style');
        });

        // Should have exactly ONE fighting style choice, not two
        $this->assertCount(1, $fightingStyleChoices, 'Expected exactly 1 fighting style choice');

        // The choice should be from OptionalFeatureChoiceHandler (type=optional_feature, subtype=fighting_style)
        $choice = $fightingStyleChoices->first();
        $this->assertEquals('optional_feature', $choice['type']);
        $this->assertEquals('fighting_style', $choice['subtype']);
        $this->assertEquals(1, $choice['quantity'], 'Should require 1 selection');
        $this->assertEquals(1, $choice['remaining'], 'Should have 1 fighting style remaining');
    }

    #[Test]
    public function it_returns_fighting_style_choices_for_champion_fighter_at_level_10(): void
    {
        // Create Fighter class
        $fighter = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'slug' => 'fighter',
        ]);

        // Create Champion subclass
        $champion = CharacterClass::factory()->create([
            'name' => 'Champion',
            'slug' => 'fighter-champion',
            'parent_class_id' => $fighter->id,
        ]);

        // Fighter gets 1 fighting style at level 1
        ClassCounter::factory()
            ->forClass($fighter)
            ->atLevel(1)
            ->noReset()
            ->create([
                'counter_name' => 'Fighting Styles Known',
                'counter_value' => 1,
            ]);

        // Champion gets additional fighting style at level 10 (total = 2)
        // In real data, subclass counter shows cumulative total
        ClassCounter::factory()
            ->forClass($champion)
            ->atLevel(10)
            ->noReset()
            ->create([
                'counter_name' => 'Fighting Styles Known',
                'counter_value' => 1, // Additional style from Champion
            ]);

        // Create level 10 Fighter with Champion subclass
        $character = Character::factory()->create();
        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_slug' => $fighter->slug,
            'subclass_slug' => $champion->slug,
            'level' => 10,
            'is_primary' => true,
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/pending-choices");

        $response->assertOk();

        $choices = $response->json('data.choices');

        // Filter to only fighting style related choices
        $fightingStyleChoices = collect($choices)->filter(function ($choice) {
            return $choice['type'] === 'optional_feature' && $choice['subtype'] === 'fighting_style';
        });

        // Should have 2 choice records: one from Fighter class (L1), one from Champion subclass (L10)
        $this->assertCount(2, $fightingStyleChoices, 'Expected 2 choice records for fighting styles');

        // Total fighting styles remaining should be 2 (1 from class + 1 from subclass)
        $totalRemaining = $fightingStyleChoices->sum('remaining');
        $this->assertEquals(2, $totalRemaining, 'Champion L10 should have 2 fighting styles total remaining');
    }
}
