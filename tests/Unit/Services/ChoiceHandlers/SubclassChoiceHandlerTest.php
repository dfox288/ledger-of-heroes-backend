<?php

declare(strict_types=1);

namespace Tests\Unit\Services\ChoiceHandlers;

use App\DTOs\PendingChoice;
use App\Exceptions\InvalidSelectionException;
use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\CharacterClassPivot;
use App\Models\ClassFeature;
use App\Services\ChoiceHandlers\SubclassChoiceHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SubclassChoiceHandlerTest extends TestCase
{
    use RefreshDatabase;

    private SubclassChoiceHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->handler = app(SubclassChoiceHandler::class);
    }

    #[Test]
    public function get_type_returns_subclass(): void
    {
        $this->assertSame('subclass', $this->handler->getType());
    }

    #[Test]
    public function returns_subclass_choice_for_cleric_at_level_1(): void
    {
        // Cleric gets Divine Domain at level 1
        $cleric = CharacterClass::factory()->create([
            'name' => 'Cleric',
            'slug' => 'cleric',
            'parent_class_id' => null,
        ]);

        // Create some subclasses
        $lifeDomain = CharacterClass::factory()->create([
            'name' => 'Life Domain',
            'slug' => 'life-domain',
            'parent_class_id' => $cleric->id,
        ]);

        $warDomain = CharacterClass::factory()->create([
            'name' => 'War Domain',
            'slug' => 'war-domain',
            'parent_class_id' => $cleric->id,
        ]);

        // Add features to subclasses to determine subclass_level
        ClassFeature::factory()->create([
            'class_id' => $lifeDomain->id,
            'feature_name' => 'Bonus Proficiency',
            'level' => 1,
        ]);

        ClassFeature::factory()->create([
            'class_id' => $warDomain->id,
            'feature_name' => 'War Priest',
            'level' => 1,
        ]);

        $character = Character::factory()->create();

        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_slug' => $cleric->full_slug,
            'subclass_slug' => null,
            'level' => 1,
            'is_primary' => true,
        ]);

        $character->load('characterClasses.characterClass.subclasses');

        $choices = $this->handler->getChoices($character);

        $this->assertCount(1, $choices);

        $choice = $choices->first();
        $this->assertInstanceOf(PendingChoice::class, $choice);
        $this->assertSame('subclass', $choice->type);
        $this->assertSame('class', $choice->source);
        $this->assertSame('Cleric', $choice->sourceName);
        $this->assertSame(1, $choice->levelGranted);
        $this->assertTrue($choice->required);
        $this->assertSame(1, $choice->quantity);
        $this->assertSame(1, $choice->remaining);
        $this->assertCount(2, $choice->options);
        $this->assertSame($cleric->full_slug, $choice->metadata['class_slug']);
    }

    #[Test]
    public function does_not_return_choice_for_fighter_at_level_1(): void
    {
        // Fighter gets Martial Archetype at level 3
        $fighter = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'slug' => 'fighter',
            'parent_class_id' => null,
        ]);

        // Create subclass
        $champion = CharacterClass::factory()->create([
            'name' => 'Champion',
            'slug' => 'champion',
            'parent_class_id' => $fighter->id,
        ]);

        // Add feature at level 3
        ClassFeature::factory()->create([
            'class_id' => $champion->id,
            'feature_name' => 'Improved Critical',
            'level' => 3,
        ]);

        $character = Character::factory()->create();

        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_slug' => $fighter->full_slug,
            'subclass_slug' => null,
            'level' => 1,
            'is_primary' => true,
        ]);

        $character->load('characterClasses.characterClass.subclasses');

        $choices = $this->handler->getChoices($character);

        $this->assertCount(0, $choices);
    }

    #[Test]
    public function returns_choice_for_fighter_at_level_3(): void
    {
        // Fighter gets Martial Archetype at level 3
        $fighter = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'slug' => 'fighter',
            'parent_class_id' => null,
        ]);

        // Create subclasses
        $champion = CharacterClass::factory()->create([
            'name' => 'Champion',
            'slug' => 'champion',
            'parent_class_id' => $fighter->id,
        ]);

        $battlemaster = CharacterClass::factory()->create([
            'name' => 'Battle Master',
            'slug' => 'battle-master',
            'parent_class_id' => $fighter->id,
        ]);

        // Add features at level 3
        ClassFeature::factory()->create([
            'class_id' => $champion->id,
            'feature_name' => 'Improved Critical',
            'level' => 3,
        ]);

        ClassFeature::factory()->create([
            'class_id' => $battlemaster->id,
            'feature_name' => 'Combat Superiority',
            'level' => 3,
        ]);

        $character = Character::factory()->create();

        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_slug' => $fighter->full_slug,
            'subclass_slug' => null,
            'level' => 3,
            'is_primary' => true,
        ]);

        $character->load('characterClasses.characterClass.subclasses');

        $choices = $this->handler->getChoices($character);

        $this->assertCount(1, $choices);

        $choice = $choices->first();
        $this->assertSame('subclass', $choice->type);
        $this->assertSame('Fighter', $choice->sourceName);
        $this->assertSame(3, $choice->levelGranted);
        $this->assertCount(2, $choice->options);
    }

    #[Test]
    public function does_not_return_choice_if_subclass_already_selected(): void
    {
        $cleric = CharacterClass::factory()->create([
            'name' => 'Cleric',
            'slug' => 'cleric',
            'parent_class_id' => null,
        ]);

        $lifeDomain = CharacterClass::factory()->create([
            'name' => 'Life Domain',
            'slug' => 'life-domain',
            'parent_class_id' => $cleric->id,
        ]);

        ClassFeature::factory()->create([
            'class_id' => $lifeDomain->id,
            'feature_name' => 'Bonus Proficiency',
            'level' => 1,
        ]);

        $character = Character::factory()->create();

        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_slug' => $cleric->full_slug,
            'subclass_slug' => $lifeDomain->full_slug, // Already selected
            'level' => 1,
            'is_primary' => true,
        ]);

        $character->load('characterClasses.characterClass.subclasses');

        $choices = $this->handler->getChoices($character);

        $this->assertCount(0, $choices);
    }

    #[Test]
    public function resolve_sets_subclass_on_character_class_pivot(): void
    {
        $cleric = CharacterClass::factory()->create([
            'name' => 'Cleric',
            'slug' => 'cleric',
            'parent_class_id' => null,
        ]);

        $lifeDomain = CharacterClass::factory()->create([
            'name' => 'Life Domain',
            'slug' => 'life-domain',
            'parent_class_id' => $cleric->id,
        ]);

        $character = Character::factory()->create();

        $pivot = CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_slug' => $cleric->full_slug,
            'subclass_slug' => null,
            'level' => 1,
            'is_primary' => true,
        ]);

        $choice = new PendingChoice(
            id: "subclass|class|{$cleric->full_slug}|1|subclass",
            type: 'subclass',
            subtype: null,
            source: 'class',
            sourceName: 'Cleric',
            levelGranted: 1,
            required: true,
            quantity: 1,
            remaining: 1,
            selected: [],
            options: [],
            optionsEndpoint: null,
            metadata: ['class_slug' => $cleric->full_slug],
        );

        $this->handler->resolve($character, $choice, [
            'subclass_slug' => $lifeDomain->full_slug,
        ]);

        $pivot->refresh();
        $this->assertSame($lifeDomain->full_slug, $pivot->subclass_slug);
    }

    #[Test]
    public function resolve_throws_exception_if_subclass_slug_not_provided(): void
    {
        $this->expectException(InvalidSelectionException::class);

        $cleric = CharacterClass::factory()->create([
            'name' => 'Cleric',
            'slug' => 'cleric',
            'parent_class_id' => null,
        ]);

        $character = Character::factory()->create();

        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_slug' => $cleric->full_slug,
            'subclass_slug' => null,
            'level' => 1,
            'is_primary' => true,
        ]);

        $choice = new PendingChoice(
            id: "subclass|class|{$cleric->full_slug}|1|subclass",
            type: 'subclass',
            subtype: null,
            source: 'class',
            sourceName: 'Cleric',
            levelGranted: 1,
            required: true,
            quantity: 1,
            remaining: 1,
            selected: [],
            options: [],
            optionsEndpoint: null,
            metadata: ['class_slug' => $cleric->full_slug],
        );

        $this->handler->resolve($character, $choice, []);
    }

    #[Test]
    public function resolve_throws_exception_if_subclass_does_not_belong_to_class(): void
    {
        $this->expectException(InvalidSelectionException::class);

        $cleric = CharacterClass::factory()->create([
            'name' => 'Cleric',
            'slug' => 'cleric',
            'parent_class_id' => null,
        ]);

        $fighter = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'slug' => 'fighter',
            'parent_class_id' => null,
        ]);

        $champion = CharacterClass::factory()->create([
            'name' => 'Champion',
            'slug' => 'champion',
            'parent_class_id' => $fighter->id, // Belongs to Fighter, not Cleric
        ]);

        $character = Character::factory()->create();

        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_slug' => $cleric->full_slug,
            'subclass_slug' => null,
            'level' => 1,
            'is_primary' => true,
        ]);

        $choice = new PendingChoice(
            id: "subclass|class|{$cleric->full_slug}|1|subclass",
            type: 'subclass',
            subtype: null,
            source: 'class',
            sourceName: 'Cleric',
            levelGranted: 1,
            required: true,
            quantity: 1,
            remaining: 1,
            selected: [],
            options: [],
            optionsEndpoint: null,
            metadata: ['class_slug' => $cleric->full_slug],
        );

        $this->handler->resolve($character, $choice, [
            'subclass_slug' => $champion->full_slug,
        ]);
    }

    #[Test]
    public function can_undo_returns_true_at_creation_level(): void
    {
        $cleric = CharacterClass::factory()->create([
            'name' => 'Cleric',
            'slug' => 'cleric',
            'parent_class_id' => null,
        ]);

        $lifeDomain = CharacterClass::factory()->create([
            'name' => 'Life Domain',
            'slug' => 'life-domain',
            'parent_class_id' => $cleric->id,
        ]);

        ClassFeature::factory()->create([
            'class_id' => $lifeDomain->id,
            'feature_name' => 'Bonus Proficiency',
            'level' => 1,
        ]);

        $character = Character::factory()->create();

        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_slug' => $cleric->full_slug,
            'subclass_slug' => $lifeDomain->full_slug,
            'level' => 1,
            'is_primary' => true,
        ]);

        $character->load('characterClasses');

        $choice = new PendingChoice(
            id: "subclass|class|{$cleric->full_slug}|1|subclass",
            type: 'subclass',
            subtype: null,
            source: 'class',
            sourceName: 'Cleric',
            levelGranted: 1,
            required: true,
            quantity: 1,
            remaining: 0,
            selected: [(string) $lifeDomain->id],
            options: [],
            optionsEndpoint: null,
            metadata: ['class_slug' => $cleric->full_slug],
        );

        $this->assertTrue($this->handler->canUndo($character, $choice));
    }

    #[Test]
    public function can_undo_returns_false_after_leveling_beyond_subclass_level(): void
    {
        $cleric = CharacterClass::factory()->create([
            'name' => 'Cleric',
            'slug' => 'cleric',
            'parent_class_id' => null,
        ]);

        $lifeDomain = CharacterClass::factory()->create([
            'name' => 'Life Domain',
            'slug' => 'life-domain',
            'parent_class_id' => $cleric->id,
        ]);

        ClassFeature::factory()->create([
            'class_id' => $lifeDomain->id,
            'feature_name' => 'Bonus Proficiency',
            'level' => 1,
        ]);

        $character = Character::factory()->create();

        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_slug' => $cleric->full_slug,
            'subclass_slug' => $lifeDomain->full_slug,
            'level' => 5, // Leveled beyond level 1
            'is_primary' => true,
        ]);

        $character->load('characterClasses');

        $choice = new PendingChoice(
            id: "subclass|class|{$cleric->full_slug}|1|subclass",
            type: 'subclass',
            subtype: null,
            source: 'class',
            sourceName: 'Cleric',
            levelGranted: 1,
            required: true,
            quantity: 1,
            remaining: 0,
            selected: [(string) $lifeDomain->id],
            options: [],
            optionsEndpoint: null,
            metadata: ['class_slug' => $cleric->full_slug],
        );

        $this->assertFalse($this->handler->canUndo($character, $choice));
    }

    #[Test]
    public function undo_clears_subclass_slug_on_pivot(): void
    {
        $cleric = CharacterClass::factory()->create([
            'name' => 'Cleric',
            'slug' => 'cleric',
            'parent_class_id' => null,
        ]);

        $lifeDomain = CharacterClass::factory()->create([
            'name' => 'Life Domain',
            'slug' => 'life-domain',
            'parent_class_id' => $cleric->id,
        ]);

        $character = Character::factory()->create();

        $pivot = CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_slug' => $cleric->full_slug,
            'subclass_slug' => $lifeDomain->full_slug,
            'level' => 1,
            'is_primary' => true,
        ]);

        $choice = new PendingChoice(
            id: "subclass|class|{$cleric->full_slug}|1|subclass",
            type: 'subclass',
            subtype: null,
            source: 'class',
            sourceName: 'Cleric',
            levelGranted: 1,
            required: true,
            quantity: 1,
            remaining: 0,
            selected: [(string) $lifeDomain->id],
            options: [],
            optionsEndpoint: null,
            metadata: ['class_slug' => $cleric->full_slug],
        );

        $this->handler->undo($character, $choice);

        $pivot->refresh();
        $this->assertNull($pivot->subclass_slug);
    }

    #[Test]
    public function generates_correct_choice_id(): void
    {
        $cleric = CharacterClass::factory()->create([
            'name' => 'Cleric',
            'slug' => 'cleric',
            'parent_class_id' => null,
        ]);

        $lifeDomain = CharacterClass::factory()->create([
            'name' => 'Life Domain',
            'slug' => 'life-domain',
            'parent_class_id' => $cleric->id,
        ]);

        ClassFeature::factory()->create([
            'class_id' => $lifeDomain->id,
            'feature_name' => 'Bonus Proficiency',
            'level' => 1,
        ]);

        $character = Character::factory()->create();

        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_slug' => $cleric->full_slug,
            'subclass_slug' => null,
            'level' => 1,
            'is_primary' => true,
        ]);

        $character->load('characterClasses.characterClass.subclasses');

        $choices = $this->handler->getChoices($character);
        $choice = $choices->first();

        $this->assertSame("subclass|class|{$cleric->full_slug}|1|subclass", $choice->id);
    }

    #[Test]
    public function includes_subclass_details_in_options(): void
    {
        $cleric = CharacterClass::factory()->create([
            'name' => 'Cleric',
            'slug' => 'cleric',
            'parent_class_id' => null,
            'description' => 'A priestly champion',
        ]);

        $lifeDomain = CharacterClass::factory()->create([
            'name' => 'Life Domain',
            'slug' => 'life-domain',
            'parent_class_id' => $cleric->id,
            'description' => 'The Life domain focuses on healing',
        ]);

        ClassFeature::factory()->create([
            'class_id' => $lifeDomain->id,
            'feature_name' => 'Bonus Proficiency',
            'level' => 1,
        ]);

        ClassFeature::factory()->create([
            'class_id' => $lifeDomain->id,
            'feature_name' => 'Disciple of Life',
            'level' => 1,
        ]);

        $character = Character::factory()->create();

        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_slug' => $cleric->full_slug,
            'subclass_slug' => null,
            'level' => 1,
            'is_primary' => true,
        ]);

        $character->load('characterClasses.characterClass.subclasses.features');

        $choices = $this->handler->getChoices($character);
        $choice = $choices->first();

        $this->assertCount(1, $choice->options);

        $option = $choice->options[0];
        $this->assertSame($lifeDomain->full_slug, $option['full_slug']);
        $this->assertSame('Life Domain', $option['name']);
        $this->assertSame('life-domain', $option['slug']);
        $this->assertSame('The Life domain focuses on healing', $option['description']);
        $this->assertIsArray($option['features_preview']);
        $this->assertContains('Bonus Proficiency', $option['features_preview']);
        $this->assertContains('Disciple of Life', $option['features_preview']);
    }

    #[Test]
    public function resolve_assigns_subclass_features_to_character(): void
    {
        $cleric = CharacterClass::factory()->create([
            'name' => 'Cleric',
            'slug' => 'cleric',
            'parent_class_id' => null,
        ]);

        $lifeDomain = CharacterClass::factory()->create([
            'name' => 'Life Domain',
            'slug' => 'life-domain',
            'parent_class_id' => $cleric->id,
        ]);

        // Create level 1 subclass features
        $bonusProficiency = ClassFeature::factory()->create([
            'class_id' => $lifeDomain->id,
            'feature_name' => 'Bonus Proficiency',
            'level' => 1,
            'is_optional' => false,
        ]);

        $discipleOfLife = ClassFeature::factory()->create([
            'class_id' => $lifeDomain->id,
            'feature_name' => 'Disciple of Life',
            'level' => 1,
            'is_optional' => false,
        ]);

        // Create a level 2 feature that should NOT be assigned yet
        ClassFeature::factory()->create([
            'class_id' => $lifeDomain->id,
            'feature_name' => 'Channel Divinity',
            'level' => 2,
            'is_optional' => false,
        ]);

        $character = Character::factory()->create();

        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_slug' => $cleric->full_slug,
            'subclass_slug' => null,
            'level' => 1,
            'is_primary' => true,
        ]);

        $choice = new PendingChoice(
            id: "subclass|class|{$cleric->full_slug}|1|subclass",
            type: 'subclass',
            subtype: null,
            source: 'class',
            sourceName: 'Cleric',
            levelGranted: 1,
            required: true,
            quantity: 1,
            remaining: 1,
            selected: [],
            options: [],
            optionsEndpoint: null,
            metadata: ['class_slug' => $cleric->full_slug],
        );

        $this->handler->resolve($character, $choice, [
            'subclass_slug' => $lifeDomain->full_slug,
        ]);

        // Verify subclass features were assigned
        $character->refresh();
        $features = $character->features;

        $this->assertCount(2, $features);

        $featureIds = $features->pluck('feature_id')->toArray();
        $this->assertContains($bonusProficiency->id, $featureIds);
        $this->assertContains($discipleOfLife->id, $featureIds);

        // Verify source is 'subclass'
        $this->assertTrue($features->every(fn ($f) => $f->source === 'subclass'));

        // Verify level_acquired is correct
        $this->assertTrue($features->every(fn ($f) => $f->level_acquired === 1));
    }

    #[Test]
    public function undo_removes_subclass_features_from_character(): void
    {
        $cleric = CharacterClass::factory()->create([
            'name' => 'Cleric',
            'slug' => 'cleric',
            'parent_class_id' => null,
        ]);

        $lifeDomain = CharacterClass::factory()->create([
            'name' => 'Life Domain',
            'slug' => 'life-domain',
            'parent_class_id' => $cleric->id,
        ]);

        $bonusProficiency = ClassFeature::factory()->create([
            'class_id' => $lifeDomain->id,
            'feature_name' => 'Bonus Proficiency',
            'level' => 1,
            'is_optional' => false,
        ]);

        $character = Character::factory()->create();

        $pivot = CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_slug' => $cleric->full_slug,
            'subclass_slug' => $lifeDomain->full_slug,
            'level' => 1,
            'is_primary' => true,
        ]);

        // Add the subclass feature to character
        $character->features()->create([
            'feature_type' => ClassFeature::class,
            'feature_id' => $bonusProficiency->id,
            'source' => 'subclass',
            'level_acquired' => 1,
        ]);

        $this->assertCount(1, $character->features);

        $choice = new PendingChoice(
            id: "subclass|class|{$cleric->full_slug}|1|subclass",
            type: 'subclass',
            subtype: null,
            source: 'class',
            sourceName: 'Cleric',
            levelGranted: 1,
            required: true,
            quantity: 1,
            remaining: 0,
            selected: [(string) $lifeDomain->id],
            options: [],
            optionsEndpoint: null,
            metadata: ['class_slug' => $cleric->full_slug],
        );

        $this->handler->undo($character, $choice);

        // Verify subclass features were removed
        $character->refresh();
        $this->assertCount(0, $character->features);

        // Verify pivot was cleared
        $pivot->refresh();
        $this->assertNull($pivot->subclass_slug);
    }

    #[Test]
    public function undo_only_removes_features_for_specific_subclass_multiclass(): void
    {
        // Test multiclass scenario: Cleric/Warlock - undoing Cleric subclass
        // should NOT remove Warlock subclass features

        $cleric = CharacterClass::factory()->create([
            'name' => 'Cleric',
            'slug' => 'cleric',
            'parent_class_id' => null,
        ]);

        $lifeDomain = CharacterClass::factory()->create([
            'name' => 'Life Domain',
            'slug' => 'life-domain',
            'parent_class_id' => $cleric->id,
        ]);

        $warlock = CharacterClass::factory()->create([
            'name' => 'Warlock',
            'slug' => 'warlock',
            'parent_class_id' => null,
        ]);

        $fiendPatron = CharacterClass::factory()->create([
            'name' => 'The Fiend',
            'slug' => 'the-fiend',
            'parent_class_id' => $warlock->id,
        ]);

        // Create features for each subclass
        $clericFeature = ClassFeature::factory()->create([
            'class_id' => $lifeDomain->id,
            'feature_name' => 'Disciple of Life',
            'level' => 1,
            'is_optional' => false,
        ]);

        $warlockFeature = ClassFeature::factory()->create([
            'class_id' => $fiendPatron->id,
            'feature_name' => "Dark One's Blessing",
            'level' => 1,
            'is_optional' => false,
        ]);

        $character = Character::factory()->create();

        // Add both classes with subclasses
        $clericPivot = CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_slug' => $cleric->full_slug,
            'subclass_slug' => $lifeDomain->full_slug,
            'level' => 1,
            'is_primary' => true,
            'order' => 1,
        ]);

        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_slug' => $warlock->full_slug,
            'subclass_slug' => $fiendPatron->full_slug,
            'level' => 1,
            'is_primary' => false,
            'order' => 2,
        ]);

        // Add both subclass features to character
        $character->features()->create([
            'feature_type' => ClassFeature::class,
            'feature_id' => $clericFeature->id,
            'source' => 'subclass',
            'level_acquired' => 1,
        ]);

        $character->features()->create([
            'feature_type' => ClassFeature::class,
            'feature_id' => $warlockFeature->id,
            'source' => 'subclass',
            'level_acquired' => 1,
        ]);

        $this->assertCount(2, $character->features);

        // Undo the CLERIC subclass choice
        $choice = new PendingChoice(
            id: "subclass|class|{$cleric->full_slug}|1|subclass",
            type: 'subclass',
            subtype: null,
            source: 'class',
            sourceName: 'Cleric',
            levelGranted: 1,
            required: true,
            quantity: 1,
            remaining: 0,
            selected: [(string) $lifeDomain->id],
            options: [],
            optionsEndpoint: null,
            metadata: ['class_slug' => $cleric->full_slug],
        );

        $this->handler->undo($character, $choice);

        // Verify only Cleric subclass features were removed
        $character->refresh();
        $this->assertCount(1, $character->features);

        // The remaining feature should be the Warlock feature
        $remainingFeature = $character->features->first();
        $this->assertSame($warlockFeature->id, $remainingFeature->feature_id);

        // Verify Cleric pivot was cleared but Warlock wasn't touched
        $clericPivot->refresh();
        $this->assertNull($clericPivot->subclass_slug);
    }
}
