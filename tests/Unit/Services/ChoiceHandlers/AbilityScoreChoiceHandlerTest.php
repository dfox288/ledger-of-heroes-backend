<?php

declare(strict_types=1);

namespace Tests\Unit\Services\ChoiceHandlers;

use App\DTOs\PendingChoice;
use App\Exceptions\InvalidSelectionException;
use App\Models\AbilityScore;
use App\Models\Character;
use App\Models\CharacterAbilityScore;
use App\Models\EntityChoice;
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
    public function it_returns_choices_for_race_with_ability_score_choices(): void
    {
        // Create a Half-Elf-like race with choice via EntityChoice
        $race = Race::factory()->create([
            'slug' => 'phb:half-elf',
            'name' => 'Half-Elf',
        ]);

        // Add ability score choice via EntityChoice
        EntityChoice::create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'choice_type' => 'ability_score',
            'choice_group' => 'ability_choice_1',
            'quantity' => 2,
            'constraints' => ['value' => '+1', 'constraint' => 'different'],
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
        $this->assertArrayHasKey('choice_group', $choice->metadata);
        $this->assertArrayHasKey('bonus_value', $choice->metadata);
        $this->assertArrayHasKey('choice_constraint', $choice->metadata);
    }

    #[Test]
    public function it_includes_parent_race_choices_for_subraces(): void
    {
        // Create parent race (Elf) with a choice
        $parentRace = Race::factory()->create([
            'slug' => 'phb:elf',
            'name' => 'Elf',
        ]);

        EntityChoice::create([
            'reference_type' => Race::class,
            'reference_id' => $parentRace->id,
            'choice_type' => 'ability_score',
            'choice_group' => 'parent_ability_choice',
            'quantity' => 1,
            'constraints' => ['value' => '+1'],
        ]);

        // Create subrace (High Elf)
        $subrace = Race::factory()->create([
            'slug' => 'phb:high-elf',
            'name' => 'High Elf',
            'parent_race_id' => $parentRace->id,
        ]);

        EntityChoice::create([
            'reference_type' => Race::class,
            'reference_id' => $subrace->id,
            'choice_type' => 'ability_score',
            'choice_group' => 'subrace_ability_choice',
            'quantity' => 1,
            'constraints' => ['value' => '+1'],
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
            'slug' => 'phb:half-elf',
            'name' => 'Half-Elf',
        ]);

        EntityChoice::create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'choice_type' => 'ability_score',
            'choice_group' => 'ability_choice_1',
            'quantity' => 2,
            'constraints' => ['value' => '+1', 'constraint' => 'different'],
        ]);

        $character = Character::factory()->create(['race_slug' => 'phb:half-elf']);

        // Select one ability score
        CharacterAbilityScore::factory()->create([
            'character_id' => $character->id,
            'ability_score_code' => 'STR',
            'bonus' => 1,
            'source' => 'race',
            'choice_group' => 'ability_choice_1',
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
            'slug' => 'phb:half-elf',
            'name' => 'Half-Elf',
        ]);

        EntityChoice::create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'choice_type' => 'ability_score',
            'choice_group' => 'ability_choice_1',
            'quantity' => 2,
            'constraints' => ['value' => '+1', 'constraint' => 'different'],
        ]);

        $character = Character::factory()->create(['race_slug' => 'phb:half-elf']);

        $choice = new PendingChoice(
            id: 'ability_score|race|phb:half-elf|1|ability_choice_1',
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
                'choice_group' => 'ability_choice_1',
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

        $choiceGroups = $character->abilityScores->pluck('choice_group')->unique()->toArray();
        $this->assertEquals(['ability_choice_1'], $choiceGroups);

        $bonuses = $character->abilityScores->pluck('bonus')->unique()->toArray();
        $this->assertEquals([1], $bonuses);
    }

    #[Test]
    public function it_validates_exact_quantity_required(): void
    {
        $race = Race::factory()->create([
            'slug' => 'phb:half-elf',
            'name' => 'Half-Elf',
        ]);

        EntityChoice::create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'choice_type' => 'ability_score',
            'choice_group' => 'ability_choice_1',
            'quantity' => 2,
            'constraints' => ['value' => '+1', 'constraint' => 'different'],
        ]);

        $character = Character::factory()->create(['race_slug' => 'phb:half-elf']);

        $choice = new PendingChoice(
            id: 'ability_score|race|phb:half-elf|1|ability_choice_1',
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
                'choice_group' => 'ability_choice_1',
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
            'slug' => 'phb:half-elf',
            'name' => 'Half-Elf',
        ]);

        EntityChoice::create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'choice_type' => 'ability_score',
            'choice_group' => 'ability_choice_1',
            'quantity' => 2,
            'constraints' => ['value' => '+1', 'constraint' => 'different'],
        ]);

        $character = Character::factory()->create(['race_slug' => 'phb:half-elf']);

        $choice = new PendingChoice(
            id: 'ability_score|race|phb:half-elf|1|ability_choice_1',
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
                'choice_group' => 'ability_choice_1',
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
            'slug' => 'phb:half-elf',
            'name' => 'Half-Elf',
        ]);

        EntityChoice::create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'choice_type' => 'ability_score',
            'choice_group' => 'ability_choice_1',
            'quantity' => 2,
            'constraints' => ['value' => '+1', 'constraint' => 'different'],
        ]);

        $character = Character::factory()->create(['race_slug' => 'phb:half-elf']);

        $choice = new PendingChoice(
            id: 'ability_score|race|phb:half-elf|1|ability_choice_1',
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
                'choice_group' => 'ability_choice_1',
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
            'slug' => 'phb:half-elf',
            'name' => 'Half-Elf',
        ]);

        EntityChoice::create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'choice_type' => 'ability_score',
            'choice_group' => 'ability_choice_1',
            'quantity' => 2,
            'constraints' => ['value' => '+1', 'constraint' => 'different'],
        ]);

        $character = Character::factory()->create(['race_slug' => 'phb:half-elf']);

        $choice = new PendingChoice(
            id: 'ability_score|race|phb:half-elf|1|ability_choice_1',
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
                'choice_group' => 'ability_choice_1',
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
            'slug' => 'phb:half-elf',
            'name' => 'Half-Elf',
        ]);

        EntityChoice::create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'choice_type' => 'ability_score',
            'choice_group' => 'ability_choice_1',
            'quantity' => 2,
            'constraints' => ['value' => '+1', 'constraint' => 'different'],
        ]);

        $character = Character::factory()->create(['race_slug' => 'phb:half-elf']);

        $choice = new PendingChoice(
            id: 'ability_score|race|phb:half-elf|1|ability_choice_1',
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
                'choice_group' => 'ability_choice_1',
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
            'slug' => 'phb:half-elf',
            'name' => 'Half-Elf',
        ]);

        EntityChoice::create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'choice_type' => 'ability_score',
            'choice_group' => 'ability_choice_1',
            'quantity' => 2,
            'constraints' => ['value' => '+1', 'constraint' => 'different'],
        ]);

        $character = Character::factory()->create(['race_slug' => 'phb:half-elf']);

        // Create selections
        CharacterAbilityScore::factory()->create([
            'character_id' => $character->id,
            'ability_score_code' => 'STR',
            'bonus' => 1,
            'source' => 'race',
            'choice_group' => 'ability_choice_1',
        ]);

        CharacterAbilityScore::factory()->create([
            'character_id' => $character->id,
            'ability_score_code' => 'DEX',
            'bonus' => 1,
            'source' => 'race',
            'choice_group' => 'ability_choice_1',
        ]);

        $choice = new PendingChoice(
            id: 'ability_score|race|phb:half-elf|1|ability_choice_1',
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
                'choice_group' => 'ability_choice_1',
                'bonus_value' => 1,
                'choice_constraint' => 'different',
            ],
        );

        // Verify selections exist
        $this->assertEquals(2, $character->abilityScores()->where('choice_group', 'ability_choice_1')->count());

        $this->handler->undo($character, $choice);

        // Verify selections were removed
        $this->assertEquals(0, $character->abilityScores()->where('choice_group', 'ability_choice_1')->count());
    }

    #[Test]
    public function it_returns_true_for_can_undo(): void
    {
        $race = Race::factory()->create([
            'slug' => 'phb:half-elf',
            'name' => 'Half-Elf',
        ]);

        $character = Character::factory()->create(['race_slug' => 'phb:half-elf']);

        $choice = new PendingChoice(
            id: 'ability_score|race|phb:half-elf|1|ability_choice_1',
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
                'choice_group' => 'ability_choice_1',
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
            'slug' => 'phb:half-elf',
            'name' => 'Half-Elf',
        ]);

        EntityChoice::create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'choice_type' => 'ability_score',
            'choice_group' => 'ability_choice_1',
            'quantity' => 2,
            'constraints' => ['value' => '+1', 'constraint' => 'different'],
        ]);

        $character = Character::factory()->create(['race_slug' => 'phb:half-elf']);

        // Create initial selections
        CharacterAbilityScore::factory()->create([
            'character_id' => $character->id,
            'ability_score_code' => 'STR',
            'bonus' => 1,
            'source' => 'race',
            'choice_group' => 'ability_choice_1',
        ]);

        CharacterAbilityScore::factory()->create([
            'character_id' => $character->id,
            'ability_score_code' => 'DEX',
            'bonus' => 1,
            'source' => 'race',
            'choice_group' => 'ability_choice_1',
        ]);

        $choice = new PendingChoice(
            id: 'ability_score|race|phb:half-elf|1|ability_choice_1',
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
                'choice_group' => 'ability_choice_1',
                'bonus_value' => 1,
                'choice_constraint' => 'different',
            ],
        );

        // Resolve with new selections
        $this->handler->resolve($character, $choice, ['selected' => ['INT', 'WIS']]);

        // Verify old selections were replaced
        $count = $character->abilityScores()->where('choice_group', 'ability_choice_1')->count();
        $this->assertEquals(2, $count);

        $codes = $character->abilityScores->pluck('ability_score_code')->toArray();
        $this->assertContains('INT', $codes);
        $this->assertContains('WIS', $codes);
        $this->assertNotContains('STR', $codes);
        $this->assertNotContains('DEX', $codes);
    }
}
