<?php

declare(strict_types=1);

namespace Tests\Unit\Services\ChoiceHandlers;

use App\DTOs\PendingChoice;
use App\Exceptions\InvalidSelectionException;
use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\CharacterClassPivot;
use App\Models\CharacterSpell;
use App\Models\ClassFeature;
use App\Models\Spell;
use App\Services\ChoiceHandlers\SubclassChoiceHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
            'class_slug' => $cleric->slug,
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
        $this->assertSame($cleric->slug, $choice->metadata['class_slug']);
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
            'class_slug' => $fighter->slug,
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
            'class_slug' => $fighter->slug,
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
            'class_slug' => $cleric->slug,
            'subclass_slug' => $lifeDomain->slug, // Already selected
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
            'class_slug' => $cleric->slug,
            'subclass_slug' => null,
            'level' => 1,
            'is_primary' => true,
        ]);

        $choice = new PendingChoice(
            id: "subclass|class|{$cleric->slug}|1|subclass",
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
            metadata: ['class_slug' => $cleric->slug],
        );

        $this->handler->resolve($character, $choice, [
            'subclass_slug' => $lifeDomain->slug,
        ]);

        $pivot->refresh();
        $this->assertSame($lifeDomain->slug, $pivot->subclass_slug);
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
            'class_slug' => $cleric->slug,
            'subclass_slug' => null,
            'level' => 1,
            'is_primary' => true,
        ]);

        $choice = new PendingChoice(
            id: "subclass|class|{$cleric->slug}|1|subclass",
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
            metadata: ['class_slug' => $cleric->slug],
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
            'class_slug' => $cleric->slug,
            'subclass_slug' => null,
            'level' => 1,
            'is_primary' => true,
        ]);

        $choice = new PendingChoice(
            id: "subclass|class|{$cleric->slug}|1|subclass",
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
            metadata: ['class_slug' => $cleric->slug],
        );

        $this->handler->resolve($character, $choice, [
            'subclass_slug' => $champion->slug,
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
            'class_slug' => $cleric->slug,
            'subclass_slug' => $lifeDomain->slug,
            'level' => 1,
            'is_primary' => true,
        ]);

        $character->load('characterClasses');

        $choice = new PendingChoice(
            id: "subclass|class|{$cleric->slug}|1|subclass",
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
            metadata: ['class_slug' => $cleric->slug],
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
            'class_slug' => $cleric->slug,
            'subclass_slug' => $lifeDomain->slug,
            'level' => 5, // Leveled beyond level 1
            'is_primary' => true,
        ]);

        $character->load('characterClasses');

        $choice = new PendingChoice(
            id: "subclass|class|{$cleric->slug}|1|subclass",
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
            metadata: ['class_slug' => $cleric->slug],
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
            'class_slug' => $cleric->slug,
            'subclass_slug' => $lifeDomain->slug,
            'level' => 1,
            'is_primary' => true,
        ]);

        $choice = new PendingChoice(
            id: "subclass|class|{$cleric->slug}|1|subclass",
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
            metadata: ['class_slug' => $cleric->slug],
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
            'class_slug' => $cleric->slug,
            'subclass_slug' => null,
            'level' => 1,
            'is_primary' => true,
        ]);

        $character->load('characterClasses.characterClass.subclasses');

        $choices = $this->handler->getChoices($character);
        $choice = $choices->first();

        $this->assertSame("subclass|class|{$cleric->slug}|1|subclass", $choice->id);
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
            'class_slug' => $cleric->slug,
            'subclass_slug' => null,
            'level' => 1,
            'is_primary' => true,
        ]);

        $character->load('characterClasses.characterClass.subclasses.features');

        $choices = $this->handler->getChoices($character);
        $choice = $choices->first();

        $this->assertCount(1, $choice->options);

        $option = $choice->options[0];
        $this->assertSame($lifeDomain->slug, $option['slug']);
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
            'class_slug' => $cleric->slug,
            'subclass_slug' => null,
            'level' => 1,
            'is_primary' => true,
        ]);

        $choice = new PendingChoice(
            id: "subclass|class|{$cleric->slug}|1|subclass",
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
            metadata: ['class_slug' => $cleric->slug],
        );

        $this->handler->resolve($character, $choice, [
            'subclass_slug' => $lifeDomain->slug,
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
            'class_slug' => $cleric->slug,
            'subclass_slug' => $lifeDomain->slug,
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
            id: "subclass|class|{$cleric->slug}|1|subclass",
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
            metadata: ['class_slug' => $cleric->slug],
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
            'class_slug' => $cleric->slug,
            'subclass_slug' => $lifeDomain->slug,
            'level' => 1,
            'is_primary' => true,
            'order' => 1,
        ]);

        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_slug' => $warlock->slug,
            'subclass_slug' => $fiendPatron->slug,
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
            id: "subclass|class|{$cleric->slug}|1|subclass",
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
            metadata: ['class_slug' => $cleric->slug],
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

    // =====================
    // Subclass Spell Assignment Tests
    // =====================

    #[Test]
    public function resolve_assigns_domain_spells_to_character_as_always_prepared(): void
    {
        // Create Cleric class (domain spells are always prepared for clerics)
        $cleric = CharacterClass::factory()->create([
            'name' => 'Cleric',
            'slug' => 'cleric',
            'parent_class_id' => null,
        ]);

        $lightDomain = CharacterClass::factory()->create([
            'name' => 'Light Domain',
            'slug' => 'light-domain',
            'parent_class_id' => $cleric->id,
        ]);

        // Create domain spells feature
        // Note: is_always_prepared is a computed accessor based on parent class name
        // (Cleric, Druid, Paladin = true; Warlock = false)
        $domainSpellsFeature = ClassFeature::factory()->create([
            'class_id' => $lightDomain->id,
            'feature_name' => 'Domain Spells',
            'level' => 1,
            'is_optional' => false,
        ]);

        // Create spells and link them to the feature
        $faerieFire = Spell::factory()->create([
            'name' => 'Faerie Fire',
            'slug' => 'faerie-fire',
            'slug' => 'test:faerie-fire',
            'level' => 1,
        ]);

        $burningHands = Spell::factory()->create([
            'name' => 'Burning Hands',
            'slug' => 'burning-hands',
            'slug' => 'test:burning-hands',
            'level' => 1,
        ]);

        // Link spells to feature via entity_spells
        DB::table('entity_spells')->insert([
            [
                'reference_type' => ClassFeature::class,
                'reference_id' => $domainSpellsFeature->id,
                'spell_id' => $faerieFire->id,
                'level_requirement' => 1,
                'is_cantrip' => false,
            ],
            [
                'reference_type' => ClassFeature::class,
                'reference_id' => $domainSpellsFeature->id,
                'spell_id' => $burningHands->id,
                'level_requirement' => 1,
                'is_cantrip' => false,
            ],
        ]);

        $character = Character::factory()->create();

        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_slug' => $cleric->slug,
            'subclass_slug' => null,
            'level' => 1,
            'is_primary' => true,
        ]);

        $choice = new PendingChoice(
            id: "subclass|class|{$cleric->slug}|1|subclass",
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
            metadata: ['class_slug' => $cleric->slug],
        );

        $this->handler->resolve($character, $choice, [
            'subclass_slug' => $lightDomain->slug,
        ]);

        // Verify spells were assigned to character
        $character->refresh();
        $characterSpells = $character->spells;

        $this->assertCount(2, $characterSpells);

        // Verify spell slugs
        $spellSlugs = $characterSpells->pluck('spell_slug')->toArray();
        $this->assertContains('test:faerie-fire', $spellSlugs);
        $this->assertContains('test:burning-hands', $spellSlugs);

        // Verify preparation status is 'always_prepared' for Cleric
        $this->assertTrue($characterSpells->every(fn ($s) => $s->preparation_status === 'always_prepared'));

        // Verify source is 'subclass'
        $this->assertTrue($characterSpells->every(fn ($s) => $s->source === 'subclass'));

        // Verify level_acquired is correct
        $this->assertTrue($characterSpells->every(fn ($s) => $s->level_acquired === 1));
    }

    #[Test]
    public function resolve_does_not_assign_warlock_expanded_spells_to_character_spells(): void
    {
        // Warlock expanded spells are NOT auto-assigned - they just expand the available
        // spell pool. The player must still choose them via SpellManagerService::getAvailableSpells()
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

        // Create expanded spells feature
        // Note: is_always_prepared is computed based on parent class name - Warlock = false
        $expandedSpellsFeature = ClassFeature::factory()->create([
            'class_id' => $fiendPatron->id,
            'feature_name' => 'Expanded Spell List',
            'level' => 1,
            'is_optional' => false,
        ]);

        // Create spell and link to feature
        $burningHands = Spell::factory()->create([
            'name' => 'Burning Hands',
            'slug' => 'burning-hands',
            'slug' => 'test:burning-hands-warlock',
            'level' => 1,
        ]);

        DB::table('entity_spells')->insert([
            'reference_type' => ClassFeature::class,
            'reference_id' => $expandedSpellsFeature->id,
            'spell_id' => $burningHands->id,
            'level_requirement' => 1,
            'is_cantrip' => false,
        ]);

        $character = Character::factory()->create();

        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_slug' => $warlock->slug,
            'subclass_slug' => null,
            'level' => 1,
            'is_primary' => true,
        ]);

        $choice = new PendingChoice(
            id: "subclass|class|{$warlock->slug}|1|subclass",
            type: 'subclass',
            subtype: null,
            source: 'class',
            sourceName: 'Warlock',
            levelGranted: 1,
            required: true,
            quantity: 1,
            remaining: 1,
            selected: [],
            options: [],
            optionsEndpoint: null,
            metadata: ['class_slug' => $warlock->slug],
        );

        $this->handler->resolve($character, $choice, [
            'subclass_slug' => $fiendPatron->slug,
        ]);

        // Verify NO spells were assigned to character_spells
        // (expanded spells just expand the pool, handled by SpellManagerService)
        $character->refresh();
        $this->assertCount(0, $character->spells);
    }

    #[Test]
    public function resolve_only_assigns_spells_at_or_below_character_level(): void
    {
        $cleric = CharacterClass::factory()->create([
            'name' => 'Cleric',
            'slug' => 'cleric',
            'parent_class_id' => null,
        ]);

        $lightDomain = CharacterClass::factory()->create([
            'name' => 'Light Domain',
            'slug' => 'light-domain',
            'parent_class_id' => $cleric->id,
        ]);

        $domainSpellsFeature = ClassFeature::factory()->create([
            'class_id' => $lightDomain->id,
            'feature_name' => 'Domain Spells',
            'level' => 1,
            'is_optional' => false,
        ]);

        // Create spells at different level requirements
        $faerieFire = Spell::factory()->create([
            'name' => 'Faerie Fire',
            'slug' => 'faerie-fire',
            'slug' => 'test:faerie-fire-level',
            'level' => 1,
        ]);

        $scorchingRay = Spell::factory()->create([
            'name' => 'Scorching Ray',
            'slug' => 'scorching-ray',
            'slug' => 'test:scorching-ray',
            'level' => 2,
        ]);

        // Link spells with different level requirements
        DB::table('entity_spells')->insert([
            [
                'reference_type' => ClassFeature::class,
                'reference_id' => $domainSpellsFeature->id,
                'spell_id' => $faerieFire->id,
                'level_requirement' => 1, // Available at level 1
                'is_cantrip' => false,
            ],
            [
                'reference_type' => ClassFeature::class,
                'reference_id' => $domainSpellsFeature->id,
                'spell_id' => $scorchingRay->id,
                'level_requirement' => 3, // Only available at level 3+
                'is_cantrip' => false,
            ],
        ]);

        $character = Character::factory()->create();

        // Character is level 1 in Cleric
        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_slug' => $cleric->slug,
            'subclass_slug' => null,
            'level' => 1,
            'is_primary' => true,
        ]);

        $choice = new PendingChoice(
            id: "subclass|class|{$cleric->slug}|1|subclass",
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
            metadata: ['class_slug' => $cleric->slug],
        );

        $this->handler->resolve($character, $choice, [
            'subclass_slug' => $lightDomain->slug,
        ]);

        // Verify only level 1 spell was assigned
        $character->refresh();
        $characterSpells = $character->spells;

        $this->assertCount(1, $characterSpells);
        $this->assertEquals('test:faerie-fire-level', $characterSpells->first()->spell_slug);
    }

    #[Test]
    public function resolve_assigns_bonus_cantrips_from_subclass_features(): void
    {
        $cleric = CharacterClass::factory()->create([
            'name' => 'Cleric',
            'slug' => 'cleric',
            'parent_class_id' => null,
        ]);

        $lightDomain = CharacterClass::factory()->create([
            'name' => 'Light Domain',
            'slug' => 'light-domain',
            'parent_class_id' => $cleric->id,
        ]);

        // Create bonus cantrip feature
        $bonusCantripFeature = ClassFeature::factory()->create([
            'class_id' => $lightDomain->id,
            'feature_name' => 'Bonus Cantrip',
            'level' => 1,
            'is_optional' => false,
        ]);

        // Create Light cantrip
        $lightCantrip = Spell::factory()->cantrip()->create([
            'name' => 'Light',
            'slug' => 'light',
            'slug' => 'test:light',
        ]);

        // Link cantrip to feature
        DB::table('entity_spells')->insert([
            'reference_type' => ClassFeature::class,
            'reference_id' => $bonusCantripFeature->id,
            'spell_id' => $lightCantrip->id,
            'level_requirement' => 1,
            'is_cantrip' => true,
        ]);

        $character = Character::factory()->create();

        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_slug' => $cleric->slug,
            'subclass_slug' => null,
            'level' => 1,
            'is_primary' => true,
        ]);

        $choice = new PendingChoice(
            id: "subclass|class|{$cleric->slug}|1|subclass",
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
            metadata: ['class_slug' => $cleric->slug],
        );

        $this->handler->resolve($character, $choice, [
            'subclass_slug' => $lightDomain->slug,
        ]);

        // Verify cantrip was assigned
        $character->refresh();
        $characterSpells = $character->spells;

        $this->assertCount(1, $characterSpells);
        $this->assertEquals('test:light', $characterSpells->first()->spell_slug);
        $this->assertEquals('always_prepared', $characterSpells->first()->preparation_status);
    }

    #[Test]
    public function undo_removes_subclass_spells_from_character(): void
    {
        $cleric = CharacterClass::factory()->create([
            'name' => 'Cleric',
            'slug' => 'cleric',
            'parent_class_id' => null,
        ]);

        $lightDomain = CharacterClass::factory()->create([
            'name' => 'Light Domain',
            'slug' => 'light-domain',
            'parent_class_id' => $cleric->id,
        ]);

        $domainSpellsFeature = ClassFeature::factory()->create([
            'class_id' => $lightDomain->id,
            'feature_name' => 'Domain Spells',
            'level' => 1,
            'is_optional' => false,
        ]);

        $faerieFire = Spell::factory()->create([
            'name' => 'Faerie Fire',
            'slug' => 'faerie-fire',
            'slug' => 'test:faerie-fire-undo',
            'level' => 1,
        ]);

        DB::table('entity_spells')->insert([
            'reference_type' => ClassFeature::class,
            'reference_id' => $domainSpellsFeature->id,
            'spell_id' => $faerieFire->id,
            'level_requirement' => 1,
            'is_cantrip' => false,
        ]);

        $character = Character::factory()->create();

        $pivot = CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_slug' => $cleric->slug,
            'subclass_slug' => $lightDomain->slug,
            'level' => 1,
            'is_primary' => true,
        ]);

        // Add subclass feature
        $character->features()->create([
            'feature_type' => ClassFeature::class,
            'feature_id' => $domainSpellsFeature->id,
            'source' => 'subclass',
            'level_acquired' => 1,
        ]);

        // Add subclass spell
        CharacterSpell::create([
            'character_id' => $character->id,
            'spell_slug' => 'test:faerie-fire-undo',
            'source' => 'subclass',
            'level_acquired' => 1,
            'preparation_status' => 'always_prepared',
        ]);

        $this->assertCount(1, $character->spells);

        $choice = new PendingChoice(
            id: "subclass|class|{$cleric->slug}|1|subclass",
            type: 'subclass',
            subtype: null,
            source: 'class',
            sourceName: 'Cleric',
            levelGranted: 1,
            required: true,
            quantity: 1,
            remaining: 0,
            selected: [(string) $lightDomain->id],
            options: [],
            optionsEndpoint: null,
            metadata: ['class_slug' => $cleric->slug],
        );

        $this->handler->undo($character, $choice);

        // Verify spells were removed
        $character->refresh();
        $this->assertCount(0, $character->spells);

        // Verify features were also removed
        $this->assertCount(0, $character->features);
    }

    #[Test]
    public function undo_only_removes_spells_for_specific_subclass_multiclass(): void
    {
        // Test multiclass scenario: Cleric/Paladin - undoing Cleric subclass
        // should NOT remove Paladin subclass spells
        // (Using Paladin instead of Warlock because Warlock expanded spells
        // don't get added to character_spells - they just expand the pool)
        $cleric = CharacterClass::factory()->create([
            'name' => 'Cleric',
            'slug' => 'cleric',
            'parent_class_id' => null,
        ]);

        $lightDomain = CharacterClass::factory()->create([
            'name' => 'Light Domain',
            'slug' => 'light-domain',
            'parent_class_id' => $cleric->id,
        ]);

        $paladin = CharacterClass::factory()->create([
            'name' => 'Paladin',
            'slug' => 'paladin',
            'parent_class_id' => null,
        ]);

        $devotionOath = CharacterClass::factory()->create([
            'name' => 'Oath of Devotion',
            'slug' => 'oath-of-devotion',
            'parent_class_id' => $paladin->id,
        ]);

        // Create features for each subclass
        // Note: is_always_prepared is computed - Cleric and Paladin both return true
        $clericFeature = ClassFeature::factory()->create([
            'class_id' => $lightDomain->id,
            'feature_name' => 'Domain Spells',
            'level' => 1,
        ]);

        $paladinFeature = ClassFeature::factory()->create([
            'class_id' => $devotionOath->id,
            'feature_name' => 'Oath Spells',
            'level' => 1,
        ]);

        // Create spells for each subclass
        $clericSpell = Spell::factory()->create([
            'name' => 'Faerie Fire',
            'slug' => 'faerie-fire-cleric',
            'slug' => 'test:faerie-fire-cleric',
            'level' => 1,
        ]);

        $paladinSpell = Spell::factory()->create([
            'name' => 'Protection from Evil and Good',
            'slug' => 'protection-from-evil-and-good',
            'slug' => 'test:protection-from-evil-and-good',
            'level' => 1,
        ]);

        // Link spells to features via entity_spells (required for undo to work)
        DB::table('entity_spells')->insert([
            [
                'reference_type' => ClassFeature::class,
                'reference_id' => $clericFeature->id,
                'spell_id' => $clericSpell->id,
                'level_requirement' => 1,
                'is_cantrip' => false,
            ],
            [
                'reference_type' => ClassFeature::class,
                'reference_id' => $paladinFeature->id,
                'spell_id' => $paladinSpell->id,
                'level_requirement' => 1,
                'is_cantrip' => false,
            ],
        ]);

        $character = Character::factory()->create();

        // Add both classes with subclasses
        $clericPivot = CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_slug' => $cleric->slug,
            'subclass_slug' => $lightDomain->slug,
            'level' => 1,
            'is_primary' => true,
            'order' => 1,
        ]);

        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_slug' => $paladin->slug,
            'subclass_slug' => $devotionOath->slug,
            'level' => 1,
            'is_primary' => false,
            'order' => 2,
        ]);

        // Add both subclass features
        $character->features()->create([
            'feature_type' => ClassFeature::class,
            'feature_id' => $clericFeature->id,
            'source' => 'subclass',
            'level_acquired' => 1,
        ]);

        $character->features()->create([
            'feature_type' => ClassFeature::class,
            'feature_id' => $paladinFeature->id,
            'source' => 'subclass',
            'level_acquired' => 1,
        ]);

        // Add spells from both subclasses (both always_prepared since both are domain/oath spells)
        CharacterSpell::create([
            'character_id' => $character->id,
            'spell_slug' => 'test:faerie-fire-cleric',
            'source' => 'subclass',
            'level_acquired' => 1,
            'preparation_status' => 'always_prepared',
        ]);

        CharacterSpell::create([
            'character_id' => $character->id,
            'spell_slug' => 'test:protection-from-evil-and-good',
            'source' => 'subclass',
            'level_acquired' => 1,
            'preparation_status' => 'always_prepared',
        ]);

        $this->assertCount(2, $character->spells);

        // Undo the CLERIC subclass choice
        $choice = new PendingChoice(
            id: "subclass|class|{$cleric->slug}|1|subclass",
            type: 'subclass',
            subtype: null,
            source: 'class',
            sourceName: 'Cleric',
            levelGranted: 1,
            required: true,
            quantity: 1,
            remaining: 0,
            selected: [(string) $lightDomain->id],
            options: [],
            optionsEndpoint: null,
            metadata: ['class_slug' => $cleric->slug],
        );

        $this->handler->undo($character, $choice);

        // Verify only Cleric subclass spells were removed
        $character->refresh();

        // Only paladin spell should remain
        $this->assertCount(1, $character->spells);
        $this->assertEquals('test:protection-from-evil-and-good', $character->spells->first()->spell_slug);

        // Only paladin feature should remain
        $this->assertCount(1, $character->features);
        $this->assertEquals($paladinFeature->id, $character->features->first()->feature_id);
    }

    #[Test]
    public function resolve_does_not_duplicate_spells_on_repeated_calls(): void
    {
        $cleric = CharacterClass::factory()->create([
            'name' => 'Cleric',
            'slug' => 'cleric',
            'parent_class_id' => null,
        ]);

        $lightDomain = CharacterClass::factory()->create([
            'name' => 'Light Domain',
            'slug' => 'light-domain',
            'parent_class_id' => $cleric->id,
        ]);

        $domainSpellsFeature = ClassFeature::factory()->create([
            'class_id' => $lightDomain->id,
            'feature_name' => 'Domain Spells',
            'level' => 1,
            'is_optional' => false,
        ]);

        $faerieFire = Spell::factory()->create([
            'name' => 'Faerie Fire',
            'slug' => 'faerie-fire',
            'slug' => 'test:faerie-fire-dup',
            'level' => 1,
        ]);

        DB::table('entity_spells')->insert([
            'reference_type' => ClassFeature::class,
            'reference_id' => $domainSpellsFeature->id,
            'spell_id' => $faerieFire->id,
            'level_requirement' => 1,
            'is_cantrip' => false,
        ]);

        $character = Character::factory()->create();

        CharacterClassPivot::factory()->create([
            'character_id' => $character->id,
            'class_slug' => $cleric->slug,
            'subclass_slug' => null,
            'level' => 1,
            'is_primary' => true,
        ]);

        $choice = new PendingChoice(
            id: "subclass|class|{$cleric->slug}|1|subclass",
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
            metadata: ['class_slug' => $cleric->slug],
        );

        // Resolve twice (simulating idempotency check)
        $this->handler->resolve($character, $choice, [
            'subclass_slug' => $lightDomain->slug,
        ]);

        // Reset the subclass_slug to test re-resolve
        CharacterClassPivot::where('character_id', $character->id)
            ->where('class_slug', $cleric->slug)
            ->update(['subclass_slug' => null]);

        $this->handler->resolve($character, $choice, [
            'subclass_slug' => $lightDomain->slug,
        ]);

        // Should still only have 1 spell (no duplicates)
        $character->refresh();
        $this->assertCount(1, $character->spells);
    }
}
