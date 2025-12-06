<?php

namespace Tests\Feature\Services\ChoiceHandlers;

use App\DTOs\PendingChoice;
use App\Models\Character;
use App\Models\Spell;
use App\Services\ChoiceHandlers\SpellChoiceHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('feature-db')]
class SpellChoiceReplacementTest extends TestCase
{
    use RefreshDatabase;

    private SpellChoiceHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->handler = new SpellChoiceHandler;
    }

    #[Test]
    public function resolving_cantrip_choice_twice_replaces_instead_of_duplicating(): void
    {
        $character = Character::factory()->create();

        // Create test cantrips
        $fireBolt = Spell::factory()->create(['name' => 'Fire Bolt', 'slug' => 'fire-bolt', 'level' => 0]);
        $rayOfFrost = Spell::factory()->create(['name' => 'Ray of Frost', 'slug' => 'ray-of-frost', 'level' => 0]);

        // Create a pending choice for cantrips
        $choice = new PendingChoice(
            id: 'spell:class:1:1:cantrips',
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
            metadata: ['spell_level' => 0, 'class_slug' => 'wizard'],
        );

        // First resolution: choose Fire Bolt
        $this->handler->resolve($character, $choice, ['selected' => [$fireBolt->id]]);
        $character->refresh();
        expect($character->spells)->toHaveCount(1);
        expect($character->spells->first()->spell_id)->toBe($fireBolt->id);

        // Second resolution: change to Ray of Frost
        $this->handler->resolve($character, $choice, ['selected' => [$rayOfFrost->id]]);
        $character->refresh();

        // Should have ONLY Ray of Frost now (Fire Bolt should be replaced)
        expect($character->spells)->toHaveCount(1)
            ->and($character->spells->first()->spell_id)->toBe($rayOfFrost->id);
    }

    #[Test]
    public function resolving_spells_known_choice_twice_replaces_instead_of_duplicating(): void
    {
        $character = Character::factory()->create();

        // Create test spells
        $magicMissile = Spell::factory()->create(['name' => 'Magic Missile', 'slug' => 'magic-missile', 'level' => 1]);
        $shield = Spell::factory()->create(['name' => 'Shield', 'slug' => 'shield', 'level' => 1]);

        // Create a pending choice for 1st level spells
        $choice = new PendingChoice(
            id: 'spell:class:1:1:spells_known',
            type: 'spell',
            subtype: 'spells_known',
            source: 'class',
            sourceName: 'Bard',
            levelGranted: 1,
            required: true,
            quantity: 2,
            remaining: 2,
            selected: [],
            options: null,
            optionsEndpoint: "/api/v1/characters/{$character->id}/available-spells?max_level=1",
            metadata: ['spell_level' => 1, 'class_slug' => 'bard'],
        );

        // First resolution: choose Magic Missile
        $this->handler->resolve($character, $choice, ['selected' => [$magicMissile->id]]);
        $character->refresh();
        expect($character->spells)->toHaveCount(1);

        // Second resolution: change to Shield
        $this->handler->resolve($character, $choice, ['selected' => [$shield->id]]);
        $character->refresh();

        // Should have ONLY Shield now
        expect($character->spells)->toHaveCount(1)
            ->and($character->spells->first()->spell_id)->toBe($shield->id);
    }

    #[Test]
    public function resolving_cantrips_does_not_affect_spells_known(): void
    {
        $character = Character::factory()->create();

        // Create test spells
        $fireBolt = Spell::factory()->create(['name' => 'Fire Bolt', 'slug' => 'fire-bolt', 'level' => 0]);
        $magicMissile = Spell::factory()->create(['name' => 'Magic Missile', 'slug' => 'magic-missile', 'level' => 1]);

        // Create cantrip choice
        $cantripChoice = new PendingChoice(
            id: 'spell:class:1:1:cantrips',
            type: 'spell',
            subtype: 'cantrip',
            source: 'class',
            sourceName: 'Wizard',
            levelGranted: 1,
            required: true,
            quantity: 1,
            remaining: 1,
            selected: [],
            options: null,
            optionsEndpoint: null,
            metadata: ['spell_level' => 0, 'class_slug' => 'wizard'],
        );

        // Create spells known choice
        $spellChoice = new PendingChoice(
            id: 'spell:class:1:1:spells_known',
            type: 'spell',
            subtype: 'spells_known',
            source: 'class',
            sourceName: 'Wizard',
            levelGranted: 1,
            required: true,
            quantity: 1,
            remaining: 1,
            selected: [],
            options: null,
            optionsEndpoint: null,
            metadata: ['spell_level' => 1, 'class_slug' => 'wizard'],
        );

        // Resolve both choices
        $this->handler->resolve($character, $cantripChoice, ['selected' => [$fireBolt->id]]);
        $this->handler->resolve($character, $spellChoice, ['selected' => [$magicMissile->id]]);
        $character->refresh();

        // Should have both spells (different choice groups: cantrips vs spells_known)
        expect($character->spells)->toHaveCount(2);
        $spellIds = $character->spells->pluck('spell_id')->toArray();
        expect($spellIds)->toContain($fireBolt->id)
            ->and($spellIds)->toContain($magicMissile->id);
    }

    #[Test]
    public function resolving_level_2_spells_does_not_affect_level_1_spells(): void
    {
        $character = Character::factory()->create();

        // Create test spells
        $level1Spell = Spell::factory()->create(['name' => 'Magic Missile', 'slug' => 'magic-missile', 'level' => 1]);
        $level2Spell = Spell::factory()->create(['name' => 'Invisibility', 'slug' => 'invisibility', 'level' => 2]);

        // Create level 1 spell choice
        $level1Choice = new PendingChoice(
            id: 'spell:class:1:1:spells_known',
            type: 'spell',
            subtype: 'spells_known',
            source: 'class',
            sourceName: 'Bard',
            levelGranted: 1,
            required: true,
            quantity: 2,
            remaining: 2,
            selected: [],
            options: null,
            optionsEndpoint: null,
            metadata: ['spell_level' => 1, 'class_slug' => 'bard'],
        );

        // Create level 2 spell choice (different level_acquired in choice ID)
        $level2Choice = new PendingChoice(
            id: 'spell:class:1:3:spells_known',  // Note: level 3 (when 2nd level spells become available)
            type: 'spell',
            subtype: 'spells_known',
            source: 'class',
            sourceName: 'Bard',
            levelGranted: 3,
            required: true,
            quantity: 1,
            remaining: 1,
            selected: [],
            options: null,
            optionsEndpoint: null,
            metadata: ['spell_level' => 2, 'class_slug' => 'bard'],
        );

        // Resolve level 1 spell choice
        $this->handler->resolve($character, $level1Choice, ['selected' => [$level1Spell->id]]);
        $character->refresh();
        expect($character->spells)->toHaveCount(1);

        // Resolve level 2 (actually level 3) spell choice
        $this->handler->resolve($character, $level2Choice, ['selected' => [$level2Spell->id]]);
        $character->refresh();

        // Should have BOTH spells (different level_acquired)
        expect($character->spells)->toHaveCount(2);
        $spellIds = $character->spells->pluck('spell_id')->toArray();
        expect($spellIds)->toContain($level1Spell->id)
            ->and($spellIds)->toContain($level2Spell->id);
    }
}
