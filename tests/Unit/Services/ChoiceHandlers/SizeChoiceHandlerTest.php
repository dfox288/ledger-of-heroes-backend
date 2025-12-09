<?php

namespace Tests\Unit\Services\ChoiceHandlers;

use App\DTOs\PendingChoice;
use App\Exceptions\InvalidSelectionException;
use App\Models\Character;
use App\Models\Race;
use App\Models\Size;
use App\Services\ChoiceHandlers\SizeChoiceHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('unit-db')]
class SizeChoiceHandlerTest extends TestCase
{
    use RefreshDatabase;

    private SizeChoiceHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->handler = new SizeChoiceHandler;

        // Ensure sizes exist
        Size::firstOrCreate(['code' => 'S', 'name' => 'Small']);
        Size::firstOrCreate(['code' => 'M', 'name' => 'Medium']);
    }

    #[Test]
    public function it_returns_correct_type(): void
    {
        $this->assertEquals('size', $this->handler->getType());
    }

    #[Test]
    public function it_returns_empty_collection_when_race_has_no_size_choice(): void
    {
        $race = Race::factory()->create(['has_size_choice' => false]);
        $character = Character::factory()->create(['race_slug' => $race->full_slug]);

        $choices = $this->handler->getChoices($character);

        $this->assertEmpty($choices);
    }

    #[Test]
    public function it_returns_size_choice_for_race_with_has_size_choice_true(): void
    {
        $race = Race::factory()->create(['has_size_choice' => true]);
        $character = Character::factory()->create(['race_slug' => $race->full_slug]);

        $choices = $this->handler->getChoices($character);

        $this->assertCount(1, $choices);
        $choice = $choices->first();
        $this->assertInstanceOf(PendingChoice::class, $choice);
        $this->assertEquals('size', $choice->type);
        $this->assertEquals('race', $choice->source);
        $this->assertEquals($race->name, $choice->sourceName);
        $this->assertEquals(1, $choice->levelGranted);
        $this->assertTrue($choice->required);
        $this->assertEquals(1, $choice->quantity);
        $this->assertEquals(1, $choice->remaining);
        $this->assertEquals([], $choice->selected);
        $this->assertNull($choice->optionsEndpoint);
    }

    #[Test]
    public function it_includes_small_and_medium_as_options(): void
    {
        $race = Race::factory()->create(['has_size_choice' => true]);
        $character = Character::factory()->create(['race_slug' => $race->full_slug]);

        $choices = $this->handler->getChoices($character);
        $options = $choices->first()->options;

        $this->assertCount(2, $options);
        $codes = array_column($options, 'code');
        $this->assertContains('S', $codes);
        $this->assertContains('M', $codes);
    }

    #[Test]
    public function it_shows_remaining_0_when_size_already_selected(): void
    {
        $race = Race::factory()->create(['has_size_choice' => true]);
        $smallSize = Size::where('code', 'S')->first();
        $character = Character::factory()->create([
            'race_slug' => $race->full_slug,
            'size_id' => $smallSize->id,
        ]);

        $choices = $this->handler->getChoices($character);

        $this->assertCount(1, $choices);
        $this->assertEquals(0, $choices->first()->remaining);
        $this->assertEquals(['S'], $choices->first()->selected);
    }

    #[Test]
    public function it_resolves_choice_with_selected_array_format(): void
    {
        $race = Race::factory()->create(['has_size_choice' => true]);
        $character = Character::factory()->create(['race_slug' => $race->full_slug]);

        $choice = new PendingChoice(
            id: "size|race|{$race->full_slug}|1|size_choice",
            type: 'size',
            subtype: null,
            source: 'race',
            sourceName: $race->name,
            levelGranted: 1,
            required: true,
            quantity: 1,
            remaining: 1,
            selected: [],
            options: [],
            optionsEndpoint: null,
            metadata: [],
        );

        $this->handler->resolve($character, $choice, ['selected' => ['S']]);

        $character->refresh();
        $this->assertNotNull($character->size_id);
        $this->assertEquals('Small', $character->size);
    }

    #[Test]
    public function it_resolves_choice_with_size_code_format(): void
    {
        $race = Race::factory()->create(['has_size_choice' => true]);
        $character = Character::factory()->create(['race_slug' => $race->full_slug]);

        $choice = new PendingChoice(
            id: "size|race|{$race->full_slug}|1|size_choice",
            type: 'size',
            subtype: null,
            source: 'race',
            sourceName: $race->name,
            levelGranted: 1,
            required: true,
            quantity: 1,
            remaining: 1,
            selected: [],
            options: [],
            optionsEndpoint: null,
            metadata: [],
        );

        $this->handler->resolve($character, $choice, ['size_code' => 'M']);

        $character->refresh();
        $this->assertEquals('Medium', $character->size);
    }

    #[Test]
    public function it_throws_exception_for_empty_selection(): void
    {
        $race = Race::factory()->create(['has_size_choice' => true]);
        $character = Character::factory()->create(['race_slug' => $race->full_slug]);

        $choice = new PendingChoice(
            id: "size|race|{$race->full_slug}|1|size_choice",
            type: 'size',
            subtype: null,
            source: 'race',
            sourceName: $race->name,
            levelGranted: 1,
            required: true,
            quantity: 1,
            remaining: 1,
            selected: [],
            options: [],
            optionsEndpoint: null,
            metadata: [],
        );

        $this->expectException(InvalidSelectionException::class);
        $this->handler->resolve($character, $choice, []);
    }

    #[Test]
    public function it_throws_exception_for_invalid_size_code(): void
    {
        $race = Race::factory()->create(['has_size_choice' => true]);
        $character = Character::factory()->create(['race_slug' => $race->full_slug]);

        $choice = new PendingChoice(
            id: "size|race|{$race->full_slug}|1|size_choice",
            type: 'size',
            subtype: null,
            source: 'race',
            sourceName: $race->name,
            levelGranted: 1,
            required: true,
            quantity: 1,
            remaining: 1,
            selected: [],
            options: [],
            optionsEndpoint: null,
            metadata: [],
        );

        $this->expectException(InvalidSelectionException::class);
        $this->handler->resolve($character, $choice, ['selected' => ['L']]);
    }

    #[Test]
    public function it_can_undo_choices(): void
    {
        $race = Race::factory()->create(['has_size_choice' => true]);
        $character = Character::factory()->create(['race_slug' => $race->full_slug]);

        $choice = new PendingChoice(
            id: "size|race|{$race->full_slug}|1|size_choice",
            type: 'size',
            subtype: null,
            source: 'race',
            sourceName: $race->name,
            levelGranted: 1,
            required: true,
            quantity: 1,
            remaining: 1,
            selected: [],
            options: [],
            optionsEndpoint: null,
            metadata: [],
        );

        $this->assertTrue($this->handler->canUndo($character, $choice));
    }

    #[Test]
    public function it_undoes_choice_by_clearing_size_id(): void
    {
        $race = Race::factory()->create(['has_size_choice' => true]);
        $smallSize = Size::where('code', 'S')->first();
        $character = Character::factory()->create([
            'race_slug' => $race->full_slug,
            'size_id' => $smallSize->id,
        ]);

        $choice = new PendingChoice(
            id: "size|race|{$race->full_slug}|1|size_choice",
            type: 'size',
            subtype: null,
            source: 'race',
            sourceName: $race->name,
            levelGranted: 1,
            required: true,
            quantity: 1,
            remaining: 0,
            selected: ['S'],
            options: [],
            optionsEndpoint: null,
            metadata: [],
        );

        $this->assertNotNull($character->size_id);

        $this->handler->undo($character, $choice);

        $character->refresh();
        $this->assertNull($character->size_id);
    }

    #[Test]
    public function it_returns_empty_collection_when_character_has_no_race(): void
    {
        $character = Character::factory()->create(['race_slug' => null]);

        $choices = $this->handler->getChoices($character);

        $this->assertEmpty($choices);
    }
}
