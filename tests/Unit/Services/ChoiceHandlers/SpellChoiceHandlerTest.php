<?php

namespace Tests\Unit\Services\ChoiceHandlers;

use App\DTOs\PendingChoice;
use App\Exceptions\InvalidSelectionException;
use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\CharacterClassPivot;
use App\Models\CharacterSpell;
use App\Models\ClassLevelProgression;
use App\Models\Spell;
use App\Services\ChoiceHandlers\SpellChoiceHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SpellChoiceHandlerTest extends TestCase
{
    use RefreshDatabase;

    private SpellChoiceHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->handler = new SpellChoiceHandler;
    }

    #[Test]
    public function it_returns_correct_type(): void
    {
        $this->assertEquals('spell', $this->handler->getType());
    }

    #[Test]
    public function it_generates_cantrip_choice_for_wizard_at_level_1(): void
    {
        // Create wizard class with progression
        $wizard = CharacterClass::factory()->create([
            'slug' => 'wizard',
            'name' => 'Wizard',
            'spellcasting_ability_id' => 4, // INT
        ]);

        ClassLevelProgression::factory()->create([
            'class_id' => $wizard->id,
            'level' => 1,
            'cantrips_known' => 3,
            'spells_known' => null,
        ]);

        // Create character with wizard class at level 1
        $character = Character::factory()->create();
        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_slug' => $wizard->full_slug,
            'level' => 1,
            'is_primary' => true,
            'order' => 1,
        ]);

        // Load relationships
        $character->load(['characterClasses.characterClass.levelProgression']);

        $choices = $this->handler->getChoices($character);

        $this->assertCount(1, $choices);
        $choice = $choices->first();
        $this->assertInstanceOf(PendingChoice::class, $choice);
        $this->assertEquals('spell', $choice->type);
        $this->assertEquals('cantrip', $choice->subtype);
        $this->assertEquals('class', $choice->source);
        $this->assertEquals('Wizard', $choice->sourceName);
        $this->assertEquals(1, $choice->levelGranted);
        $this->assertEquals(3, $choice->quantity);
        $this->assertEquals(3, $choice->remaining);
        $this->assertEquals([], $choice->selected);
        $this->assertStringContainsString('max_level=0', $choice->optionsEndpoint);
        $this->assertEquals(0, $choice->metadata['spell_level']);
        $this->assertEquals($wizard->full_slug, $choice->metadata['class_slug']);
    }

    #[Test]
    public function it_generates_spell_choice_for_known_caster_sorcerer_at_level_1(): void
    {
        // Create sorcerer class with progression
        $sorcerer = CharacterClass::factory()->create([
            'slug' => 'sorcerer',
            'name' => 'Sorcerer',
            'spellcasting_ability_id' => 6, // CHA
        ]);

        ClassLevelProgression::factory()->create([
            'class_id' => $sorcerer->id,
            'level' => 1,
            'cantrips_known' => 4,
            'spells_known' => 2,
        ]);

        // Create character with sorcerer class at level 1
        $character = Character::factory()->create();
        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_slug' => $sorcerer->full_slug,
            'level' => 1,
            'is_primary' => true,
            'order' => 1,
        ]);

        // Load relationships
        $character->load(['characterClasses.characterClass.levelProgression']);

        $choices = $this->handler->getChoices($character);

        $this->assertCount(2, $choices);

        // First should be cantrip choice
        $cantripChoice = $choices->firstWhere('subtype', 'cantrip');
        $this->assertInstanceOf(PendingChoice::class, $cantripChoice);
        $this->assertEquals('spell', $cantripChoice->type);
        $this->assertEquals('cantrip', $cantripChoice->subtype);
        $this->assertEquals(4, $cantripChoice->quantity);
        $this->assertEquals(4, $cantripChoice->remaining);

        // Second should be spells known choice
        $spellChoice = $choices->firstWhere('subtype', 'spells_known');
        $this->assertInstanceOf(PendingChoice::class, $spellChoice);
        $this->assertEquals('spell', $spellChoice->type);
        $this->assertEquals('spells_known', $spellChoice->subtype);
        $this->assertEquals(2, $spellChoice->quantity);
        $this->assertEquals(2, $spellChoice->remaining);
    }

    #[Test]
    public function it_generates_only_cantrip_choice_for_prepared_caster_cleric_at_level_1(): void
    {
        // Create cleric class with progression
        $cleric = CharacterClass::factory()->create([
            'slug' => 'cleric',
            'name' => 'Cleric',
            'spellcasting_ability_id' => 5, // WIS
        ]);

        ClassLevelProgression::factory()->create([
            'class_id' => $cleric->id,
            'level' => 1,
            'cantrips_known' => 3,
            'spells_known' => null, // Prepared caster
        ]);

        // Create character with cleric class at level 1
        $character = Character::factory()->create();
        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_slug' => $cleric->full_slug,
            'level' => 1,
            'is_primary' => true,
            'order' => 1,
        ]);

        // Load relationships
        $character->load(['characterClasses.characterClass.levelProgression']);

        $choices = $this->handler->getChoices($character);

        // Only cantrip choice, no spells_known choice for prepared casters
        $this->assertCount(1, $choices);
        $this->assertEquals('cantrip', $choices->first()->subtype);
        $this->assertEquals(3, $choices->first()->quantity);
    }

    #[Test]
    public function it_accounts_for_already_known_spells_when_calculating_remaining(): void
    {
        // Create bard class with progression
        $bard = CharacterClass::factory()->create([
            'slug' => 'bard',
            'name' => 'Bard',
            'spellcasting_ability_id' => 6, // CHA
        ]);

        ClassLevelProgression::factory()->create([
            'class_id' => $bard->id,
            'level' => 1,
            'cantrips_known' => 2,
            'spells_known' => 4,
        ]);

        // Create character with bard class at level 1
        $character = Character::factory()->create();
        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_slug' => $bard->full_slug,
            'level' => 1,
            'is_primary' => true,
            'order' => 1,
        ]);

        // Add some already known cantrips
        $spell1 = Spell::factory()->create(['level' => 0]);
        $spell2 = Spell::factory()->create(['level' => 0]);
        CharacterSpell::create([
            'character_id' => $character->id,
            'spell_slug' => $spell1->full_slug,
            'source' => 'class',
            'level_acquired' => 1,
        ]);
        CharacterSpell::create([
            'character_id' => $character->id,
            'spell_slug' => $spell2->full_slug,
            'source' => 'class',
            'level_acquired' => 1,
        ]);

        // Add 1 already known spell
        $spell3 = Spell::factory()->create(['level' => 1]);
        CharacterSpell::create([
            'character_id' => $character->id,
            'spell_slug' => $spell3->full_slug,
            'source' => 'class',
            'level_acquired' => 1,
        ]);

        // Load relationships
        $character->load(['characterClasses.characterClass.levelProgression', 'spells.spell']);

        $choices = $this->handler->getChoices($character);

        $this->assertCount(2, $choices);

        // Cantrip choice should show 2 selected, 0 remaining
        $cantripChoice = $choices->firstWhere('subtype', 'cantrip');
        $this->assertEquals(0, $cantripChoice->remaining);
        $this->assertCount(2, $cantripChoice->selected);

        // Spell choice should show 1 selected, 3 remaining
        $spellChoice = $choices->firstWhere('subtype', 'spells_known');
        $this->assertEquals(3, $spellChoice->remaining);
        $this->assertCount(1, $spellChoice->selected);
    }

    #[Test]
    public function it_does_not_generate_choices_for_non_spellcasters(): void
    {
        // Create barbarian class (non-caster)
        $barbarian = CharacterClass::factory()->create([
            'slug' => 'barbarian',
            'name' => 'Barbarian',
            'spellcasting_ability_id' => null, // No spellcasting
        ]);

        $character = Character::factory()->create();
        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_slug' => $barbarian->full_slug,
            'level' => 1,
            'is_primary' => true,
            'order' => 1,
        ]);

        $character->load(['characterClasses.characterClass.levelProgression']);

        $choices = $this->handler->getChoices($character);

        $this->assertCount(0, $choices);
    }

    #[Test]
    public function it_resolves_cantrip_choice_by_creating_character_spell_records(): void
    {
        $character = Character::factory()->create();
        $wizard = CharacterClass::factory()->create(['slug' => 'wizard']);

        $spell1 = Spell::factory()->create(['level' => 0]);
        $spell2 = Spell::factory()->create(['level' => 0]);
        $spell3 = Spell::factory()->create(['level' => 0]);

        $choice = new PendingChoice(
            id: "spell|class|{$wizard->full_slug}|1|cantrips",
            type: 'spell',
            subtype: 'cantrip',
            source: 'class',
            sourceName: 'Wizard',
            levelGranted: 1,
            required: true,
            quantity: 3,
            remaining: 3,
            selected: [],
            options: null,
            optionsEndpoint: "/api/v1/characters/{$character->id}/available-spells?max_level=0",
            metadata: [
                'spell_level' => 0,
                'class_slug' => 'wizard',
            ]
        );

        $this->handler->resolve($character, $choice, ['selected' => [$spell1->full_slug, $spell2->full_slug, $spell3->full_slug]]);

        // Verify CharacterSpell records were created
        $this->assertEquals(3, CharacterSpell::where('character_id', $character->id)->count());

        $characterSpells = CharacterSpell::where('character_id', $character->id)->get();
        $this->assertContains($spell1->full_slug, $characterSpells->pluck('spell_slug'));
        $this->assertContains($spell2->full_slug, $characterSpells->pluck('spell_slug'));
        $this->assertContains($spell3->full_slug, $characterSpells->pluck('spell_slug'));

        $this->assertTrue($characterSpells->every(fn ($cs) => $cs->source === 'class'));
        $this->assertTrue($characterSpells->every(fn ($cs) => $cs->level_acquired === 1));
    }

    #[Test]
    public function it_resolves_spells_known_choice_by_creating_character_spell_records(): void
    {
        $character = Character::factory()->create();
        $sorcerer = CharacterClass::factory()->create(['slug' => 'sorcerer']);

        $spell1 = Spell::factory()->create(['level' => 1]);
        $spell2 = Spell::factory()->create(['level' => 1]);

        $choice = new PendingChoice(
            id: "spell|class|{$sorcerer->full_slug}|1|spells_known",
            type: 'spell',
            subtype: 'spells_known',
            source: 'class',
            sourceName: 'Sorcerer',
            levelGranted: 1,
            required: true,
            quantity: 2,
            remaining: 2,
            selected: [],
            options: null,
            optionsEndpoint: "/api/v1/characters/{$character->id}/available-spells?max_level=1",
            metadata: [
                'spell_level' => 1,
                'class_slug' => 'sorcerer',
            ]
        );

        $this->handler->resolve($character, $choice, ['selected' => [$spell1->full_slug, $spell2->full_slug]]);

        // Verify CharacterSpell records were created
        $this->assertEquals(2, CharacterSpell::where('character_id', $character->id)->count());
    }

    #[Test]
    public function it_throws_exception_when_selection_is_empty(): void
    {
        $character = Character::factory()->create();
        $wizard = CharacterClass::factory()->create(['slug' => 'wizard']);

        $choice = new PendingChoice(
            id: "spell|class|{$wizard->full_slug}|1|cantrips",
            type: 'spell',
            subtype: 'cantrip',
            source: 'class',
            sourceName: 'Wizard',
            levelGranted: 1,
            required: true,
            quantity: 3,
            remaining: 3,
            selected: [],
            options: null,
            optionsEndpoint: "/api/v1/characters/{$character->id}/available-spells?max_level=0",
            metadata: ['spell_level' => 0, 'class_slug' => 'wizard']
        );

        $this->expectException(InvalidSelectionException::class);
        $this->handler->resolve($character, $choice, ['selected' => []]);
    }

    #[Test]
    public function it_throws_exception_when_spell_ids_do_not_exist(): void
    {
        $character = Character::factory()->create();
        $wizard = CharacterClass::factory()->create(['slug' => 'wizard']);

        $choice = new PendingChoice(
            id: "spell|class|{$wizard->full_slug}|1|cantrips",
            type: 'spell',
            subtype: 'cantrip',
            source: 'class',
            sourceName: 'Wizard',
            levelGranted: 1,
            required: true,
            quantity: 3,
            remaining: 3,
            selected: [],
            options: null,
            optionsEndpoint: "/api/v1/characters/{$character->id}/available-spells?max_level=0",
            metadata: ['spell_level' => 0, 'class_slug' => 'wizard']
        );

        $this->expectException(InvalidSelectionException::class);
        $this->handler->resolve($character, $choice, ['selected' => [999999]]);
    }

    #[Test]
    public function it_returns_true_for_can_undo(): void
    {
        $character = Character::factory()->create();
        $wizard = CharacterClass::factory()->create(['slug' => 'wizard']);

        $choice = new PendingChoice(
            id: "spell|class|{$wizard->full_slug}|1|cantrips",
            type: 'spell',
            subtype: 'cantrip',
            source: 'class',
            sourceName: 'Wizard',
            levelGranted: 1,
            required: true,
            quantity: 3,
            remaining: 0,
            selected: ['1', '2', '3'],
            options: null,
            optionsEndpoint: "/api/v1/characters/{$character->id}/available-spells?max_level=0",
            metadata: ['spell_level' => 0, 'class_slug' => 'wizard']
        );

        $this->assertTrue($this->handler->canUndo($character, $choice));
    }

    #[Test]
    public function it_undoes_choice_by_removing_character_spell_records(): void
    {
        $character = Character::factory()->create();
        $wizard = CharacterClass::factory()->create(['slug' => 'wizard']);

        $spell1 = Spell::factory()->create(['level' => 0]);
        $spell2 = Spell::factory()->create(['level' => 0]);
        $spell3 = Spell::factory()->create(['level' => 0]);

        // Create CharacterSpell records
        CharacterSpell::create([
            'character_id' => $character->id,
            'spell_slug' => $spell1->full_slug,
            'source' => 'class',
            'level_acquired' => 1,
        ]);
        CharacterSpell::create([
            'character_id' => $character->id,
            'spell_slug' => $spell2->full_slug,
            'source' => 'class',
            'level_acquired' => 1,
        ]);
        CharacterSpell::create([
            'character_id' => $character->id,
            'spell_slug' => $spell3->full_slug,
            'source' => 'class',
            'level_acquired' => 1,
        ]);

        $this->assertEquals(3, CharacterSpell::where('character_id', $character->id)->count());

        $choice = new PendingChoice(
            id: "spell|class|{$wizard->full_slug}|1|cantrips",
            type: 'spell',
            subtype: 'cantrip',
            source: 'class',
            sourceName: 'Wizard',
            levelGranted: 1,
            required: true,
            quantity: 3,
            remaining: 0,
            selected: [$spell1->id, $spell2->id, $spell3->id],
            options: null,
            optionsEndpoint: "/api/v1/characters/{$character->id}/available-spells?max_level=0",
            metadata: ['spell_level' => 0, 'class_slug' => 'wizard']
        );

        $this->handler->undo($character, $choice);

        // Verify CharacterSpell records were deleted
        $this->assertEquals(0, CharacterSpell::where('character_id', $character->id)->count());
    }

    #[Test]
    public function it_skips_choices_for_classes_without_progression_data(): void
    {
        // Create character with class but no progression
        $wizard = CharacterClass::factory()->create([
            'slug' => 'wizard',
            'name' => 'Wizard',
            'spellcasting_ability_id' => 4,
        ]);

        $character = Character::factory()->create();
        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_slug' => $wizard->full_slug,
            'level' => 1,
            'is_primary' => true,
            'order' => 1,
        ]);

        // Load relationships (no progression exists)
        $character->load(['characterClasses.characterClass.levelProgression']);

        $choices = $this->handler->getChoices($character);

        $this->assertCount(0, $choices);
    }

    #[Test]
    public function it_rejects_selection_exceeding_quantity_limit(): void
    {
        $character = Character::factory()->create();
        $wizard = CharacterClass::factory()->create(['slug' => 'wizard']);

        // Create 4 cantrips
        $spell1 = Spell::factory()->create(['level' => 0]);
        $spell2 = Spell::factory()->create(['level' => 0]);
        $spell3 = Spell::factory()->create(['level' => 0]);
        $spell4 = Spell::factory()->create(['level' => 0]);

        // Choice allows only 2 cantrips
        $choice = new PendingChoice(
            id: "spell|class|{$wizard->full_slug}|1|cantrips",
            type: 'spell',
            subtype: 'cantrip',
            source: 'class',
            sourceName: 'Wizard',
            levelGranted: 1,
            required: true,
            quantity: 2,
            remaining: 2,
            selected: [],
            options: null,
            optionsEndpoint: "/api/v1/characters/{$character->id}/available-spells?max_level=0",
            metadata: [
                'spell_level' => 0,
                'class_slug' => 'wizard',
            ]
        );

        // Attempt to select 4 cantrips when only 2 allowed
        $this->expectException(InvalidSelectionException::class);
        $this->expectExceptionMessage('exceeds');

        $this->handler->resolve($character, $choice, [
            'selected' => [$spell1->full_slug, $spell2->full_slug, $spell3->full_slug, $spell4->full_slug],
        ]);
    }

    #[Test]
    public function it_generates_spell_choice_for_subclass_feature_nature_domain_druid_cantrip(): void
    {
        // Create cleric class and nature domain subclass
        $cleric = CharacterClass::factory()->create([
            'slug' => 'cleric',
            'name' => 'Cleric',
            'spellcasting_ability_id' => 5, // WIS
        ]);

        $natureDomain = CharacterClass::factory()->create([
            'slug' => 'nature-domain',
            'name' => 'Nature Domain',
            'parent_class_id' => $cleric->id,
        ]);

        // Create a subclass feature for Nature Domain at level 1
        $feature = \App\Models\ClassFeature::factory()->create([
            'class_id' => $natureDomain->id,
            'level' => 1,
            'feature_name' => 'Acolyte of Nature',
        ]);

        // Create a druid class for the spell choice filter
        $druid = CharacterClass::factory()->create([
            'slug' => 'druid',
            'name' => 'Druid',
        ]);

        // Create spell choice record for the feature (1 druid cantrip)
        \App\Models\EntitySpell::create([
            'reference_type' => \App\Models\ClassFeature::class,
            'reference_id' => $feature->id,
            'spell_id' => null,
            'is_choice' => true,
            'choice_count' => 1,
            'is_cantrip' => true,
            'max_level' => 0,
            'class_id' => $druid->id,
        ]);

        // Create character with cleric and nature domain subclass
        $character = Character::factory()->create();
        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_slug' => $cleric->full_slug,
            'subclass_slug' => $natureDomain->full_slug,
            'level' => 1,
            'is_primary' => true,
            'order' => 1,
        ]);

        // Load relationships
        $character->load(['characterClasses.characterClass', 'characterClasses.subclass.features']);

        $choices = $this->handler->getChoices($character);

        // Should have the subclass feature spell choice
        $subclassChoice = $choices->firstWhere('source', 'subclass_feature');
        $this->assertNotNull($subclassChoice);
        $this->assertInstanceOf(PendingChoice::class, $subclassChoice);
        $this->assertEquals('spell', $subclassChoice->type);
        $this->assertEquals('cantrip', $subclassChoice->subtype);
        $this->assertEquals('subclass_feature', $subclassChoice->source);
        $this->assertEquals('Acolyte of Nature', $subclassChoice->sourceName);
        $this->assertEquals(1, $subclassChoice->levelGranted);
        $this->assertEquals(1, $subclassChoice->quantity);
        $this->assertEquals(1, $subclassChoice->remaining);
        $this->assertEquals([], $subclassChoice->selected);
        $this->assertStringContainsString('max_level=0', $subclassChoice->optionsEndpoint);
        $this->assertStringContainsString("class={$druid->full_slug}", $subclassChoice->optionsEndpoint);
        $this->assertEquals(0, $subclassChoice->metadata['spell_level']);
        $this->assertEquals($druid->full_slug, $subclassChoice->metadata['class_slug']);
        $this->assertEquals($feature->id, $subclassChoice->metadata['feature_id']);
    }
}
