<?php

namespace Tests\Feature\Api;

use App\Models\CharacterClass;
use App\Models\Spell;
use App\Models\SpellSchool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('feature-db')]
class ClassSpellListTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    #[Test]
    public function it_returns_spells_for_a_class()
    {
        $wizard = CharacterClass::factory()->create(['name' => 'Wizard', 'slug' => 'wizard']);

        $spell1 = Spell::factory()->create(['name' => 'Fireball', 'level' => 3]);
        $spell2 = Spell::factory()->create(['name' => 'Magic Missile', 'level' => 1]);
        $spell3 = Spell::factory()->create(['name' => 'Cure Wounds', 'level' => 1]);

        // Attach spells to wizard
        $wizard->spells()->attach([$spell1->id, $spell2->id]);

        // spell3 is NOT a wizard spell

        $response = $this->getJson('/api/v1/classes/wizard/spells');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('data.0.name', 'Fireball');
        $response->assertJsonPath('data.1.name', 'Magic Missile');
    }

    #[Test]
    public function it_filters_class_spells_by_level()
    {
        $wizard = CharacterClass::factory()->create(['name' => 'Wizard', 'slug' => 'wizard']);

        $spell1 = Spell::factory()->create(['name' => 'Fireball', 'level' => 3]);
        $spell2 = Spell::factory()->create(['name' => 'Magic Missile', 'level' => 1]);
        $spell3 = Spell::factory()->create(['name' => 'Fly', 'level' => 3]);

        $wizard->spells()->attach([$spell1->id, $spell2->id, $spell3->id]);

        // Filter for level 3 spells only
        $response = $this->getJson('/api/v1/classes/wizard/spells?level=3');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $this->assertTrue(
            collect($response->json('data'))->pluck('name')->contains('Fireball')
        );
        $this->assertTrue(
            collect($response->json('data'))->pluck('name')->contains('Fly')
        );
    }

    #[Test]
    public function it_filters_class_spells_by_school()
    {
        $wizard = CharacterClass::factory()->create(['name' => 'Wizard', 'slug' => 'wizard']);
        $evocation = SpellSchool::where('code', 'EV')->first();
        $abjuration = SpellSchool::where('code', 'A')->first();

        $spell1 = Spell::factory()->create([
            'name' => 'Fireball',
            'spell_school_id' => $evocation->id,
        ]);
        $spell2 = Spell::factory()->create([
            'name' => 'Magic Missile',
            'spell_school_id' => $abjuration->id,
        ]);

        $wizard->spells()->attach([$spell1->id, $spell2->id]);

        $response = $this->getJson("/api/v1/classes/wizard/spells?school={$evocation->id}");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Fireball');
    }

    #[Test]
    public function it_supports_slug_routing_for_class_spells()
    {
        $wizard = CharacterClass::factory()->create(['name' => 'Wizard', 'slug' => 'wizard']);
        $spell = Spell::factory()->create(['name' => 'Fireball']);
        $wizard->spells()->attach($spell->id);

        // Test with slug
        $response = $this->getJson('/api/v1/classes/wizard/spells');
        $response->assertOk();

        // Test with ID
        $response = $this->getJson("/api/v1/classes/{$wizard->id}/spells");
        $response->assertOk();
    }

    #[Test]
    public function it_paginates_class_spell_lists()
    {
        $wizard = CharacterClass::factory()->create(['name' => 'Wizard', 'slug' => 'wizard']);

        $spells = Spell::factory()->count(30)->create();
        $wizard->spells()->attach($spells->pluck('id'));

        $response = $this->getJson('/api/v1/classes/wizard/spells?per_page=10');

        $response->assertOk();
        $response->assertJsonCount(10, 'data');
        $response->assertJsonPath('meta.total', 30);
        $response->assertJsonPath('meta.per_page', 10);
    }

    #[Test]
    public function it_filters_class_spells_by_concentration()
    {
        $wizard = CharacterClass::factory()->create(['name' => 'Wizard', 'slug' => 'wizard']);

        $concentrationSpell = Spell::factory()->create([
            'name' => 'Haste',
            'needs_concentration' => true,
        ]);
        $nonConcentrationSpell = Spell::factory()->create([
            'name' => 'Fireball',
            'needs_concentration' => false,
        ]);

        $wizard->spells()->attach([$concentrationSpell->id, $nonConcentrationSpell->id]);

        $response = $this->getJson('/api/v1/classes/wizard/spells?concentration=1');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Haste');
    }

    #[Test]
    public function it_filters_class_spells_by_ritual()
    {
        $wizard = CharacterClass::factory()->create(['name' => 'Wizard', 'slug' => 'wizard']);

        $ritualSpell = Spell::factory()->create([
            'name' => 'Detect Magic',
            'is_ritual' => true,
        ]);
        $nonRitualSpell = Spell::factory()->create([
            'name' => 'Fireball',
            'is_ritual' => false,
        ]);

        $wizard->spells()->attach([$ritualSpell->id, $nonRitualSpell->id]);

        $response = $this->getJson('/api/v1/classes/wizard/spells?ritual=1');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Detect Magic');
    }

    #[Test]
    public function it_searches_class_spells_by_name()
    {
        $wizard = CharacterClass::factory()->create(['name' => 'Wizard', 'slug' => 'wizard']);

        $spell1 = Spell::factory()->create(['name' => 'Fireball']);
        $spell2 = Spell::factory()->create(['name' => 'Fire Bolt']);
        $spell3 = Spell::factory()->create(['name' => 'Magic Missile']);

        $wizard->spells()->attach([$spell1->id, $spell2->id, $spell3->id]);

        $response = $this->getJson('/api/v1/classes/wizard/spells?search=fire');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $this->assertTrue(
            collect($response->json('data'))->pluck('name')->contains('Fireball')
        );
        $this->assertTrue(
            collect($response->json('data'))->pluck('name')->contains('Fire Bolt')
        );
    }

    #[Test]
    public function it_sorts_class_spell_lists()
    {
        $wizard = CharacterClass::factory()->create(['name' => 'Wizard', 'slug' => 'wizard']);

        $spell1 = Spell::factory()->create(['name' => 'Zebra Spell', 'level' => 1]);
        $spell2 = Spell::factory()->create(['name' => 'Alpha Spell', 'level' => 2]);
        $spell3 = Spell::factory()->create(['name' => 'Beta Spell', 'level' => 3]);

        $wizard->spells()->attach([$spell1->id, $spell2->id, $spell3->id]);

        // Sort by name ascending (default)
        $response = $this->getJson('/api/v1/classes/wizard/spells?sort_by=name&sort_direction=asc');
        $response->assertOk();
        $response->assertJsonPath('data.0.name', 'Alpha Spell');
        $response->assertJsonPath('data.2.name', 'Zebra Spell');

        // Sort by level descending
        $response = $this->getJson('/api/v1/classes/wizard/spells?sort_by=level&sort_direction=desc');
        $response->assertOk();
        $response->assertJsonPath('data.0.level', 3);
        $response->assertJsonPath('data.2.level', 1);
    }

    #[Test]
    public function it_returns_empty_for_class_with_no_spells()
    {
        $fighter = CharacterClass::factory()->create(['name' => 'Fighter', 'slug' => 'fighter']);

        // Fighter has no spells attached

        $response = $this->getJson('/api/v1/classes/fighter/spells');

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
    }

    #[Test]
    public function it_combines_multiple_filters_for_class_spells()
    {
        $wizard = CharacterClass::factory()->create(['name' => 'Wizard', 'slug' => 'wizard']);
        $evocation = SpellSchool::where('code', 'EV')->first();

        $spell1 = Spell::factory()->create([
            'name' => 'Fireball',
            'level' => 3,
            'spell_school_id' => $evocation->id,
            'needs_concentration' => false,
        ]);
        $spell2 = Spell::factory()->create([
            'name' => 'Wall of Fire',
            'level' => 4,
            'spell_school_id' => $evocation->id,
            'needs_concentration' => true,
        ]);
        $spell3 = Spell::factory()->create([
            'name' => 'Magic Missile',
            'level' => 1,
        ]);

        $wizard->spells()->attach([$spell1->id, $spell2->id, $spell3->id]);

        // Filter for level 3+ evocation spells
        $response = $this->getJson("/api/v1/classes/wizard/spells?level=3&school={$evocation->id}");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Fireball');
    }
}
