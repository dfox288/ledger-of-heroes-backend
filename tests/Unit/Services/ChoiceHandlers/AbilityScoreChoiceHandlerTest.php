<?php

declare(strict_types=1);

namespace Tests\Unit\Services\ChoiceHandlers;

use App\DTOs\PendingChoice;
use App\Exceptions\InvalidSelectionException;
use App\Models\AbilityScore;
use App\Models\Character;
use App\Models\CharacterAbilityScore;
use App\Models\Modifier;
use App\Models\Race;
use App\Services\ChoiceHandlers\AbilityScoreChoiceHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AbilityScoreChoiceHandlerTest extends TestCase
{
    use RefreshDatabase;

    private AbilityScoreChoiceHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed ability scores
        $abilities = [
            'STR' => 'Strength',
            'DEX' => 'Dexterity',
            'CON' => 'Constitution',
            'INT' => 'Intelligence',
            'WIS' => 'Wisdom',
            'CHA' => 'Charisma',
        ];

        foreach ($abilities as $code => $name) {
            AbilityScore::firstOrCreate(['code' => $code], ['name' => $name]);
        }

        $this->handler = new AbilityScoreChoiceHandler;
    }

    #[Test]
    public function it_returns_correct_type(): void
    {
        $this->assertEquals('ability_score', $this->handler->getType());
    }

    #[Test]
    public function it_returns_empty_collection_when_character_has_no_race(): void
    {
        $character = Character::factory()->create(['race_slug' => null]);

        $choices = $this->handler->getChoices($character);

        $this->assertCount(0, $choices);
    }

    #[Test]
    public function it_returns_choices_for_race_with_is_choice_ability_modifiers(): void
    {
        // Create a Half-Elf-like race with choice modifiers
        $race = Race::factory()->create([
            'slug' => 'half-elf',
            'name' => 'Half-Elf',
            'slug' => 'phb:half-elf',
        ]);

        // Add a modifier with is_choice = true, choice_count = 2
        $modifier = Modifier::factory()->create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'modifier_category' => 'ability_score',
            'is_choice' => true,
            'choice_count' => 2,
            'choice_constraint' => 'different',
            'value' => 1,
        ]);

        $character = Character::factory()->create(['race_slug' => 'phb:half-elf']);

        $choices = $this->handler->getChoices($character);

        $this->assertCount(1, $choices);

        $choice = $choices->first();
        $this->assertInstanceOf(PendingChoice::class, $choice);
        $this->assertEquals('ability_score', $choice->type);
        $this->assertEquals('race', $choice->source);
        $this->assertEquals('Half-Elf', $choice->sourceName);
        $this->assertEquals(2, $choice->quantity);
        $this->assertEquals(2, $choice->remaining);
        $this->assertEquals([], $choice->selected);
        $this->assertCount(6, $choice->options);
        $this->assertArrayHasKey('modifier_id', $choice->metadata);
        $this->assertArrayHasKey('bonus_value', $choice->metadata);
        $this->assertArrayHasKey('choice_constraint', $choice->metadata);
    }

    #[Test]
    public function it_includes_parent_race_modifiers_for_subraces(): void
    {
        // Create parent race (Elf) with a choice modifier
        $parentRace = Race::factory()->create([
            'slug' => 'elf',
            'name' => 'Elf',
            'slug' => 'phb:elf',
        ]);

        $parentModifier = Modifier::factory()->create([
            'reference_type' => Race::class,
            'reference_id' => $parentRace->id,
            'modifier_category' => 'ability_score',
            'is_choice' => true,
            'choice_count' => 1,
            'choice_constraint' => null,
            'value' => 1,
        ]);

        // Create subrace (High Elf)
        $subrace = Race::factory()->create([
            'slug' => 'high-elf',
            'name' => 'High Elf',
            'slug' => 'phb:high-elf',
            'parent_race_id' => $parentRace->id,
        ]);

        $subraceModifier = Modifier::factory()->create([
            'reference_type' => Race::class,
            'reference_id' => $subrace->id,
            'modifier_category' => 'ability_score',
            'is_choice' => true,
            'choice_count' => 1,
            'choice_constraint' => null,
            'value' => 1,
        ]);

        $character = Character::factory()->create(['race_slug' => 'phb:high-elf']);

        $choices = $this->handler->getChoices($character);

        // Should have 2 choices: one from parent, one from subrace
        $this->assertCount(2, $choices);
    }

    #[Test]
    public function it_shows_correct_remaining_count_based_on_existing_selections(): void
    {
        $race = Race::factory()->create([
            'slug' => 'half-elf',
            'name' => 'Half-Elf',
            'slug' => 'phb:half-elf',
        ]);

        $modifier = Modifier::factory()->create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'modifier_category' => 'ability_score',
            'is_choice' => true,
            'choice_count' => 2,
            'choice_constraint' => 'different',
            'value' => 1,
        ]);

        $character = Character::factory()->create(['race_slug' => 'phb:half-elf']);

        // Select one ability score
        CharacterAbilityScore::factory()->create([
            'character_id' => $character->id,
            'ability_score_code' => 'STR',
            'bonus' => 1,
            'source' => 'race',
            'modifier_id' => $modifier->id,
        ]);

        $choices = $this->handler->getChoices($character);

        $this->assertCount(1, $choices);
        $choice = $choices->first();
        $this->assertEquals(2, $choice->quantity);
        $this->assertEquals(1, $choice->remaining);
        $this->assertEquals(['STR'], $choice->selected);
    }

    #[Test]
    public function it_stores_ability_score_selections_correctly(): void
    {
        $race = Race::factory()->create([
            'slug' => 'half-elf',
            'name' => 'Half-Elf',
            'slug' => 'phb:half-elf',
        ]);

        $modifier = Modifier::factory()->create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'modifier_category' => 'ability_score',
            'is_choice' => true,
            'choice_count' => 2,
            'choice_constraint' => 'different',
            'value' => 1,
        ]);

        $character = Character::factory()->create(['race_slug' => 'phb:half-elf']);

        $choice = new PendingChoice(
            id: "ability_score|race|phb:half-elf|1|modifier_{$modifier->id}",
            type: 'ability_score',
            subtype: null,
            source: 'race',
            sourceName: 'Half-Elf',
            levelGranted: 1,
            required: true,
            quantity: 2,
            remaining: 2,
            selected: [],
            options: [],
            optionsEndpoint: null,
            metadata: [
                'modifier_id' => $modifier->id,
                'bonus_value' => 1,
                'choice_constraint' => 'different',
            ],
        );

        $this->handler->resolve($character, $choice, ['selected' => ['STR', 'DEX']]);

        // Verify selections were stored
        $this->assertCount(2, $character->abilityScores);

        $codes = $character->abilityScores->pluck('ability_score_code')->toArray();
        $this->assertContains('STR', $codes);
        $this->assertContains('DEX', $codes);

        $modifierIds = $character->abilityScores->pluck('modifier_id')->unique()->toArray();
        $this->assertEquals([$modifier->id], $modifierIds);

        $bonuses = $character->abilityScores->pluck('bonus')->unique()->toArray();
        $this->assertEquals([1], $bonuses);
    }

    #[Test]
    public function it_validates_exact_quantity_required(): void
    {
        $race = Race::factory()->create([
            'slug' => 'half-elf',
            'name' => 'Half-Elf',
            'slug' => 'phb:half-elf',
        ]);

        $modifier = Modifier::factory()->create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'modifier_category' => 'ability_score',
            'is_choice' => true,
            'choice_count' => 2,
            'choice_constraint' => 'different',
            'value' => 1,
        ]);

        $character = Character::factory()->create(['race_slug' => 'phb:half-elf']);

        $choice = new PendingChoice(
            id: "ability_score|race|phb:half-elf|1|modifier_{$modifier->id}",
            type: 'ability_score',
            subtype: null,
            source: 'race',
            sourceName: 'Half-Elf',
            levelGranted: 1,
            required: true,
            quantity: 2,
            remaining: 2,
            selected: [],
            options: [],
            optionsEndpoint: null,
            metadata: [
                'modifier_id' => $modifier->id,
                'bonus_value' => 1,
                'choice_constraint' => 'different',
            ],
        );

        // Try to select only 1 when 2 required
        $this->expectException(InvalidSelectionException::class);
        $this->expectExceptionMessage('Must select exactly 2 ability score(s)');
        $this->handler->resolve($character, $choice, ['selected' => ['STR']]);
    }

    #[Test]
    public function it_validates_exact_quantity_required_rejects_too_many(): void
    {
        $race = Race::factory()->create([
            'slug' => 'half-elf',
            'name' => 'Half-Elf',
            'slug' => 'phb:half-elf',
        ]);

        $modifier = Modifier::factory()->create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'modifier_category' => 'ability_score',
            'is_choice' => true,
            'choice_count' => 2,
            'choice_constraint' => 'different',
            'value' => 1,
        ]);

        $character = Character::factory()->create(['race_slug' => 'phb:half-elf']);

        $choice = new PendingChoice(
            id: "ability_score|race|phb:half-elf|1|modifier_{$modifier->id}",
            type: 'ability_score',
            subtype: null,
            source: 'race',
            sourceName: 'Half-Elf',
            levelGranted: 1,
            required: true,
            quantity: 2,
            remaining: 2,
            selected: [],
            options: [],
            optionsEndpoint: null,
            metadata: [
                'modifier_id' => $modifier->id,
                'bonus_value' => 1,
                'choice_constraint' => 'different',
            ],
        );

        // Try to select 3 when 2 required
        $this->expectException(InvalidSelectionException::class);
        $this->expectExceptionMessage('Must select exactly 2 ability score(s)');
        $this->handler->resolve($character, $choice, ['selected' => ['STR', 'DEX', 'CON']]);
    }

    #[Test]
    public function it_validates_different_constraint_throws_exception_for_duplicates(): void
    {
        $race = Race::factory()->create([
            'slug' => 'half-elf',
            'name' => 'Half-Elf',
            'slug' => 'phb:half-elf',
        ]);

        $modifier = Modifier::factory()->create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'modifier_category' => 'ability_score',
            'is_choice' => true,
            'choice_count' => 2,
            'choice_constraint' => 'different',
            'value' => 1,
        ]);

        $character = Character::factory()->create(['race_slug' => 'phb:half-elf']);

        $choice = new PendingChoice(
            id: "ability_score|race|phb:half-elf|1|modifier_{$modifier->id}",
            type: 'ability_score',
            subtype: null,
            source: 'race',
            sourceName: 'Half-Elf',
            levelGranted: 1,
            required: true,
            quantity: 2,
            remaining: 2,
            selected: [],
            options: [],
            optionsEndpoint: null,
            metadata: [
                'modifier_id' => $modifier->id,
                'bonus_value' => 1,
                'choice_constraint' => 'different',
            ],
        );

        // Try to select same ability twice
        $this->expectException(InvalidSelectionException::class);
        $this->expectExceptionMessage('Selected ability scores must be different');
        $this->handler->resolve($character, $choice, ['selected' => ['STR', 'STR']]);
    }

    #[Test]
    public function it_rejects_invalid_ability_codes(): void
    {
        $race = Race::factory()->create([
            'slug' => 'half-elf',
            'name' => 'Half-Elf',
            'slug' => 'phb:half-elf',
        ]);

        $modifier = Modifier::factory()->create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'modifier_category' => 'ability_score',
            'is_choice' => true,
            'choice_count' => 2,
            'choice_constraint' => 'different',
            'value' => 1,
        ]);

        $character = Character::factory()->create(['race_slug' => 'phb:half-elf']);

        $choice = new PendingChoice(
            id: "ability_score|race|phb:half-elf|1|modifier_{$modifier->id}",
            type: 'ability_score',
            subtype: null,
            source: 'race',
            sourceName: 'Half-Elf',
            levelGranted: 1,
            required: true,
            quantity: 2,
            remaining: 2,
            selected: [],
            options: [],
            optionsEndpoint: null,
            metadata: [
                'modifier_id' => $modifier->id,
                'bonus_value' => 1,
                'choice_constraint' => 'different',
            ],
        );

        // Try to select invalid ability codes
        $this->expectException(InvalidSelectionException::class);
        $this->expectExceptionMessage('Invalid ability score code: INVALID');
        $this->handler->resolve($character, $choice, ['selected' => ['STR', 'INVALID']]);
    }

    #[Test]
    public function it_throws_exception_when_selection_is_empty(): void
    {
        $race = Race::factory()->create([
            'slug' => 'half-elf',
            'name' => 'Half-Elf',
            'slug' => 'phb:half-elf',
        ]);

        $modifier = Modifier::factory()->create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'modifier_category' => 'ability_score',
            'is_choice' => true,
            'choice_count' => 2,
            'choice_constraint' => 'different',
            'value' => 1,
        ]);

        $character = Character::factory()->create(['race_slug' => 'phb:half-elf']);

        $choice = new PendingChoice(
            id: "ability_score|race|phb:half-elf|1|modifier_{$modifier->id}",
            type: 'ability_score',
            subtype: null,
            source: 'race',
            sourceName: 'Half-Elf',
            levelGranted: 1,
            required: true,
            quantity: 2,
            remaining: 2,
            selected: [],
            options: [],
            optionsEndpoint: null,
            metadata: [
                'modifier_id' => $modifier->id,
                'bonus_value' => 1,
                'choice_constraint' => 'different',
            ],
        );

        $this->expectException(InvalidSelectionException::class);
        $this->expectExceptionMessage('Selection cannot be empty');
        $this->handler->resolve($character, $choice, ['selected' => []]);
    }

    #[Test]
    public function it_removes_selections_when_undo_is_called(): void
    {
        $race = Race::factory()->create([
            'slug' => 'half-elf',
            'name' => 'Half-Elf',
            'slug' => 'phb:half-elf',
        ]);

        $modifier = Modifier::factory()->create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'modifier_category' => 'ability_score',
            'is_choice' => true,
            'choice_count' => 2,
            'choice_constraint' => 'different',
            'value' => 1,
        ]);

        $character = Character::factory()->create(['race_slug' => 'phb:half-elf']);

        // Create selections
        CharacterAbilityScore::factory()->create([
            'character_id' => $character->id,
            'ability_score_code' => 'STR',
            'bonus' => 1,
            'source' => 'race',
            'modifier_id' => $modifier->id,
        ]);

        CharacterAbilityScore::factory()->create([
            'character_id' => $character->id,
            'ability_score_code' => 'DEX',
            'bonus' => 1,
            'source' => 'race',
            'modifier_id' => $modifier->id,
        ]);

        $choice = new PendingChoice(
            id: "ability_score|race|phb:half-elf|1|modifier_{$modifier->id}",
            type: 'ability_score',
            subtype: null,
            source: 'race',
            sourceName: 'Half-Elf',
            levelGranted: 1,
            required: true,
            quantity: 2,
            remaining: 0,
            selected: ['STR', 'DEX'],
            options: [],
            optionsEndpoint: null,
            metadata: [
                'modifier_id' => $modifier->id,
                'bonus_value' => 1,
                'choice_constraint' => 'different',
            ],
        );

        // Verify selections exist
        $this->assertEquals(2, $character->abilityScores()->where('modifier_id', $modifier->id)->count());

        $this->handler->undo($character, $choice);

        // Verify selections were removed
        $this->assertEquals(0, $character->abilityScores()->where('modifier_id', $modifier->id)->count());
    }

    #[Test]
    public function it_returns_true_for_can_undo(): void
    {
        $race = Race::factory()->create([
            'slug' => 'half-elf',
            'name' => 'Half-Elf',
            'slug' => 'phb:half-elf',
        ]);

        $modifier = Modifier::factory()->create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'modifier_category' => 'ability_score',
            'is_choice' => true,
            'choice_count' => 2,
            'choice_constraint' => 'different',
            'value' => 1,
        ]);

        $character = Character::factory()->create(['race_slug' => 'phb:half-elf']);

        $choice = new PendingChoice(
            id: "ability_score|race|phb:half-elf|1|modifier_{$modifier->id}",
            type: 'ability_score',
            subtype: null,
            source: 'race',
            sourceName: 'Half-Elf',
            levelGranted: 1,
            required: true,
            quantity: 2,
            remaining: 0,
            selected: ['STR', 'DEX'],
            options: [],
            optionsEndpoint: null,
            metadata: [
                'modifier_id' => $modifier->id,
                'bonus_value' => 1,
                'choice_constraint' => 'different',
            ],
        );

        $this->assertTrue($this->handler->canUndo($character, $choice));
    }

    #[Test]
    public function it_replaces_existing_selections_when_resolving_again(): void
    {
        $race = Race::factory()->create([
            'slug' => 'half-elf',
            'name' => 'Half-Elf',
            'slug' => 'phb:half-elf',
        ]);

        $modifier = Modifier::factory()->create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'modifier_category' => 'ability_score',
            'is_choice' => true,
            'choice_count' => 2,
            'choice_constraint' => 'different',
            'value' => 1,
        ]);

        $character = Character::factory()->create(['race_slug' => 'phb:half-elf']);

        // Create initial selections
        CharacterAbilityScore::factory()->create([
            'character_id' => $character->id,
            'ability_score_code' => 'STR',
            'bonus' => 1,
            'source' => 'race',
            'modifier_id' => $modifier->id,
        ]);

        CharacterAbilityScore::factory()->create([
            'character_id' => $character->id,
            'ability_score_code' => 'DEX',
            'bonus' => 1,
            'source' => 'race',
            'modifier_id' => $modifier->id,
        ]);

        $choice = new PendingChoice(
            id: "ability_score|race|phb:half-elf|1|modifier_{$modifier->id}",
            type: 'ability_score',
            subtype: null,
            source: 'race',
            sourceName: 'Half-Elf',
            levelGranted: 1,
            required: true,
            quantity: 2,
            remaining: 0,
            selected: ['STR', 'DEX'],
            options: [],
            optionsEndpoint: null,
            metadata: [
                'modifier_id' => $modifier->id,
                'bonus_value' => 1,
                'choice_constraint' => 'different',
            ],
        );

        // Resolve with new selections
        $this->handler->resolve($character, $choice, ['selected' => ['INT', 'WIS']]);

        // Verify old selections were replaced
        $count = $character->abilityScores()->where('modifier_id', $modifier->id)->count();
        $this->assertEquals(2, $count);

        $codes = $character->abilityScores->pluck('ability_score_code')->toArray();
        $this->assertContains('INT', $codes);
        $this->assertContains('WIS', $codes);
        $this->assertNotContains('STR', $codes);
        $this->assertNotContains('DEX', $codes);
    }
}
