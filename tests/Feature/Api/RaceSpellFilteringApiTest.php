<?php

namespace Tests\Feature\Api;

use App\Models\Race;
use App\Models\Spell;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RaceSpellFilteringApiTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_races_by_single_spell(): void
    {
        // Create spells
        $mistyStep = Spell::factory()->create(['name' => 'Misty Step', 'slug' => 'misty-step', 'level' => 2]);
        $dancingLights = Spell::factory()->create(['name' => 'Dancing Lights', 'slug' => 'dancing-lights', 'level' => 0]);

        // Create races
        $eladrin = Race::factory()->create(['name' => 'Eladrin', 'slug' => 'eladrin']);
        $drow = Race::factory()->create(['name' => 'Drow', 'slug' => 'drow-dark']);
        $human = Race::factory()->create(['name' => 'Human', 'slug' => 'human']);

        // Sync spells to races (Eladrin gets Misty Step, Drow gets Dancing Lights)
        $eladrin->entitySpells()->attach($mistyStep->id);
        $drow->entitySpells()->attach($dancingLights->id);

        // Test filtering by misty-step
        $response = $this->getJson('/api/v1/races?spells=misty-step');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Eladrin');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_races_by_multiple_spells_with_and_logic(): void
    {
        // Create spells
        $dancingLights = Spell::factory()->create(['name' => 'Dancing Lights', 'slug' => 'dancing-lights', 'level' => 0]);
        $faerieFire = Spell::factory()->create(['name' => 'Faerie Fire', 'slug' => 'faerie-fire', 'level' => 1]);
        $darkness = Spell::factory()->create(['name' => 'Darkness', 'slug' => 'darkness', 'level' => 2]);

        // Create races
        $drow = Race::factory()->create(['name' => 'Drow', 'slug' => 'drow-dark']);
        $highElf = Race::factory()->create(['name' => 'High Elf', 'slug' => 'high-elf']);

        // Drow gets all 3 spells
        $drow->entitySpells()->attach([$dancingLights->id, $faerieFire->id, $darkness->id]);
        // High Elf only gets Dancing Lights
        $highElf->entitySpells()->attach($dancingLights->id);

        // Test AND logic - must have BOTH spells
        $response = $this->getJson('/api/v1/races?spells=dancing-lights,faerie-fire');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Drow');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_races_by_multiple_spells_with_or_logic(): void
    {
        // Create spells
        $thaumaturgy = Spell::factory()->create(['name' => 'Thaumaturgy', 'slug' => 'thaumaturgy', 'level' => 0]);
        $hellishRebuke = Spell::factory()->create(['name' => 'Hellish Rebuke', 'slug' => 'hellish-rebuke', 'level' => 1]);

        // Create races
        $tiefling = Race::factory()->create(['name' => 'Tiefling', 'slug' => 'tiefling']);
        $asmodeusTiefling = Race::factory()->create(['name' => 'Asmodeus Tiefling', 'slug' => 'asmodeus-tiefling']);
        $human = Race::factory()->create(['name' => 'Human', 'slug' => 'human']);

        // Tiefling gets Thaumaturgy
        $tiefling->entitySpells()->attach($thaumaturgy->id);
        // Asmodeus Tiefling gets Hellish Rebuke
        $asmodeusTiefling->entitySpells()->attach($hellishRebuke->id);

        // Test OR logic - must have AT LEAST ONE spell
        $response = $this->getJson('/api/v1/races?spells=thaumaturgy,hellish-rebuke&spells_operator=OR');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $names = collect($response->json('data'))->pluck('name')->sort()->values()->all();
        $this->assertEquals(['Asmodeus Tiefling', 'Tiefling'], $names);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_races_by_spell_level(): void
    {
        // Create spells
        $minorIllusion = Spell::factory()->create(['name' => 'Minor Illusion', 'slug' => 'minor-illusion', 'level' => 0]);
        $faerieFire = Spell::factory()->create(['name' => 'Faerie Fire', 'slug' => 'faerie-fire', 'level' => 1]);

        // Create races
        $forestGnome = Race::factory()->create(['name' => 'Forest Gnome', 'slug' => 'forest-gnome']);
        $drow = Race::factory()->create(['name' => 'Drow', 'slug' => 'drow-dark']);

        // Forest Gnome gets cantrip (level 0)
        $forestGnome->entitySpells()->attach($minorIllusion->id);
        // Drow gets level 1 spell
        $drow->entitySpells()->attach($faerieFire->id);

        // Test filtering by cantrips (level 0)
        $response = $this->getJson('/api/v1/races?spell_level=0');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Forest Gnome');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_races_with_innate_spells(): void
    {
        // Create spells
        $spell = Spell::factory()->create(['name' => 'Test Spell', 'slug' => 'test-spell']);

        // Create races
        $drow = Race::factory()->create(['name' => 'Drow', 'slug' => 'drow-dark']);
        $human = Race::factory()->create(['name' => 'Human', 'slug' => 'human']);

        // Drow gets spell
        $drow->entitySpells()->attach($spell->id);

        // Test has_innate_spells filter
        $response = $this->getJson('/api/v1/races?has_innate_spells=true');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Drow');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_combines_spell_and_level_filters(): void
    {
        // Create spells
        $darkness = Spell::factory()->create(['name' => 'Darkness', 'slug' => 'darkness', 'level' => 2]);
        $faerieFire = Spell::factory()->create(['name' => 'Faerie Fire', 'slug' => 'faerie-fire', 'level' => 1]);

        // Create races
        $drow = Race::factory()->create(['name' => 'Drow', 'slug' => 'drow-dark']);
        $tiefling = Race::factory()->create(['name' => 'Tiefling', 'slug' => 'tiefling']);

        // Both get Darkness (level 2)
        $drow->entitySpells()->attach($darkness->id);
        $tiefling->entitySpells()->attach($darkness->id);

        // Drow also gets Faerie Fire (level 1)
        $drow->entitySpells()->attach($faerieFire->id);

        // Test combined: has darkness AND has level 1 spells
        $response = $this->getJson('/api/v1/races?spells=darkness&spell_level=1');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Drow');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_defaults_to_and_operator_when_not_specified(): void
    {
        // Create spells
        $spell1 = Spell::factory()->create(['name' => 'Spell One', 'slug' => 'spell-one']);
        $spell2 = Spell::factory()->create(['name' => 'Spell Two', 'slug' => 'spell-two']);

        // Create races
        $race1 = Race::factory()->create(['name' => 'Race One', 'slug' => 'race-one']);
        $race2 = Race::factory()->create(['name' => 'Race Two', 'slug' => 'race-two']);

        // Race 1 gets both spells
        $race1->entitySpells()->attach([$spell1->id, $spell2->id]);
        // Race 2 only gets spell 1
        $race2->entitySpells()->attach($spell1->id);

        // Test without operator (should default to AND)
        $response = $this->getJson('/api/v1/races?spells=spell-one,spell-two');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Race One');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_empty_results_when_no_race_has_specified_spell(): void
    {
        // Create spell
        $fireball = Spell::factory()->create(['name' => 'Fireball', 'slug' => 'fireball']);

        // Create race without spells
        Race::factory()->create(['name' => 'Human', 'slug' => 'human']);

        // Test filtering by spell no race has
        $response = $this->getJson('/api/v1/races?spells=fireball');

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_spell_filtering_case_insensitively(): void
    {
        // Create spell
        $mistyStep = Spell::factory()->create(['name' => 'Misty Step', 'slug' => 'misty-step']);

        // Create race
        $eladrin = Race::factory()->create(['name' => 'Eladrin', 'slug' => 'eladrin']);
        $eladrin->entitySpells()->attach($mistyStep->id);

        // Test with different case variations
        $response1 = $this->getJson('/api/v1/races?spells=MISTY-STEP');
        $response2 = $this->getJson('/api/v1/races?spells=Misty-Step');
        $response3 = $this->getJson('/api/v1/races?spells=misty-step');

        // All should return the same result
        $response1->assertOk()->assertJsonCount(1, 'data');
        $response2->assertOk()->assertJsonCount(1, 'data');
        $response3->assertOk()->assertJsonCount(1, 'data');
    }
}
