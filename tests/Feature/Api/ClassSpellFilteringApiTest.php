<?php

namespace Tests\Feature\Api;

use App\Models\CharacterClass;
use App\Models\Spell;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClassSpellFilteringApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test spells with known slugs
        $fireball = Spell::factory()->create([
            'name' => 'Fireball',
            'slug' => 'fireball',
            'level' => 3,
        ]);

        $counterspell = Spell::factory()->create([
            'name' => 'Counterspell',
            'slug' => 'counterspell',
            'level' => 3,
        ]);

        $cureWounds = Spell::factory()->create([
            'name' => 'Cure Wounds',
            'slug' => 'cure-wounds',
            'level' => 1,
        ]);

        $wish = Spell::factory()->create([
            'name' => 'Wish',
            'slug' => 'wish',
            'level' => 9,
        ]);

        // Create test classes with spell relationships
        $wizard = CharacterClass::factory()->create([
            'name' => 'Wizard',
            'slug' => 'wizard',
        ]);
        $wizard->spells()->attach([$fireball->id, $counterspell->id, $wish->id]);

        $sorcerer = CharacterClass::factory()->create([
            'name' => 'Sorcerer',
            'slug' => 'sorcerer',
        ]);
        $sorcerer->spells()->attach([$fireball->id, $wish->id]);

        $cleric = CharacterClass::factory()->create([
            'name' => 'Cleric',
            'slug' => 'cleric',
        ]);
        $cleric->spells()->attach([$cureWounds->id, $wish->id]);

        $fighter = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'slug' => 'fighter',
        ]);
        // Fighter has no spells
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_classes_by_single_spell(): void
    {
        $response = $this->getJson('/api/v1/classes?spells=fireball');

        $response->assertOk()
            ->assertJsonCount(2, 'data') // Wizard and Sorcerer
            ->assertJsonFragment(['slug' => 'wizard'])
            ->assertJsonFragment(['slug' => 'sorcerer'])
            ->assertJsonMissing(['slug' => 'cleric'])
            ->assertJsonMissing(['slug' => 'fighter']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_classes_by_multiple_spells_with_and_logic(): void
    {
        $response = $this->getJson('/api/v1/classes?spells=fireball,counterspell');

        $response->assertOk()
            ->assertJsonCount(1, 'data') // Only Wizard has BOTH
            ->assertJsonFragment(['slug' => 'wizard'])
            ->assertJsonMissing(['slug' => 'sorcerer']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_classes_by_multiple_spells_with_or_logic(): void
    {
        $response = $this->getJson('/api/v1/classes?spells=fireball,cure-wounds&spells_operator=OR');

        $response->assertOk()
            ->assertJsonCount(3, 'data') // Wizard, Sorcerer, Cleric
            ->assertJsonFragment(['slug' => 'wizard'])
            ->assertJsonFragment(['slug' => 'sorcerer'])
            ->assertJsonFragment(['slug' => 'cleric'])
            ->assertJsonMissing(['slug' => 'fighter']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_classes_by_spell_level(): void
    {
        $response = $this->getJson('/api/v1/classes?spell_level=9');

        $response->assertOk()
            ->assertJsonCount(3, 'data') // Wizard, Sorcerer, Cleric (all have Wish)
            ->assertJsonFragment(['slug' => 'wizard'])
            ->assertJsonFragment(['slug' => 'sorcerer'])
            ->assertJsonFragment(['slug' => 'cleric']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_combines_spell_and_spell_level_filters(): void
    {
        $response = $this->getJson('/api/v1/classes?spells=cure-wounds&spell_level=1');

        $response->assertOk()
            ->assertJsonCount(1, 'data') // Only Cleric
            ->assertJsonFragment(['slug' => 'cleric']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_defaults_to_and_operator_for_backward_compatibility(): void
    {
        // Without explicit operator, should use AND logic
        $response = $this->getJson('/api/v1/classes?spells=fireball,counterspell');

        $response->assertOk()
            ->assertJsonCount(1, 'data') // Only Wizard has BOTH
            ->assertJsonFragment(['slug' => 'wizard']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_empty_results_for_nonexistent_spell(): void
    {
        $response = $this->getJson('/api/v1/classes?spells=nonexistent-spell-9999');

        $response->assertOk()
            ->assertJsonCount(0, 'data');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_spell_slugs_case_insensitively(): void
    {
        $response = $this->getJson('/api/v1/classes?spells=FIREBALL');

        $response->assertOk()
            ->assertJsonCount(2, 'data') // Wizard and Sorcerer
            ->assertJsonFragment(['slug' => 'wizard'])
            ->assertJsonFragment(['slug' => 'sorcerer']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_combines_spell_filter_with_base_only_filter(): void
    {
        // Create a subclass that also has fireball
        $evoker = CharacterClass::factory()->create([
            'name' => 'School of Evocation',
            'slug' => 'school-of-evocation',
            'parent_class_id' => CharacterClass::where('slug', 'wizard')->first()->id,
        ]);
        $fireball = Spell::where('slug', 'fireball')->first();
        $evoker->spells()->attach($fireball->id);

        // Filter for base classes with fireball
        $response = $this->getJson('/api/v1/classes?spells=fireball&base_only=1');

        $response->assertOk()
            ->assertJsonCount(2, 'data'); // Wizard and Sorcerer (base classes only)

        // Verify the response contains only base classes
        $classNames = collect($response->json('data'))->pluck('name')->all();
        $this->assertContains('Wizard', $classNames);
        $this->assertContains('Sorcerer', $classNames);
        $this->assertNotContains('School of Evocation', $classNames);
    }
}
