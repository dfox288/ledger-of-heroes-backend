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
        $fireBolt = Spell::factory()->create(['name' => 'Fire Bolt', 'slug' => 'fire-bolt', 'slug' => 'test:fire-bolt', 'level' => 0]);
        $rayOfFrost = Spell::factory()->create(['name' => 'Ray of Frost', 'slug' => 'ray-of-frost', 'slug' => 'test:ray-of-frost', 'level' => 0]);

        // Create a pending choice for cantrips
        $choice = new PendingChoice(
            id: 'spell|class|test:wizard|1|cantrips',
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
            metadata: ['spell_level' => 0, 'class_slug' => 'test:wizard'],
        );

        // First resolution: choose Fire Bolt
        $this->handler->resolve($character, $choice, ['selected' => [$fireBolt->slug]]);
        $character->refresh();
        expect($character->spells)->toHaveCount(1);
        expect($character->spells->first()->spell_slug)->toBe($fireBolt->slug);

        // Second resolution: change to Ray of Frost
        $this->handler->resolve($character, $choice, ['selected' => [$rayOfFrost->slug]]);
        $character->refresh();

        // Should have ONLY Ray of Frost now (Fire Bolt should be replaced)
        expect($character->spells)->toHaveCount(1)
            ->and($character->spells->first()->spell_slug)->toBe($rayOfFrost->slug);
    }

    #[Test]
    public function resolving_spells_known_choice_twice_replaces_instead_of_duplicating(): void
    {
        $character = Character::factory()->create();

        // Create test spells
        $magicMissile = Spell::factory()->create(['name' => 'Magic Missile', 'slug' => 'magic-missile', 'slug' => 'test:magic-missile', 'level' => 1]);
        $shield = Spell::factory()->create(['name' => 'Shield', 'slug' => 'shield', 'slug' => 'test:shield', 'level' => 1]);

        // Create a pending choice for 1st level spells
        $choice = new PendingChoice(
            id: 'spell|class|test:bard|1|spells_known',
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
            metadata: ['spell_level' => 1, 'class_slug' => 'test:bard'],
        );

        // First resolution: choose Magic Missile
        $this->handler->resolve($character, $choice, ['selected' => [$magicMissile->slug]]);
        $character->refresh();
        expect($character->spells)->toHaveCount(1);

        // Second resolution: change to Shield
        $this->handler->resolve($character, $choice, ['selected' => [$shield->slug]]);
        $character->refresh();

        // Should have ONLY Shield now
        expect($character->spells)->toHaveCount(1)
            ->and($character->spells->first()->spell_slug)->toBe($shield->slug);
    }

    #[Test]
    public function resolving_cantrips_does_not_affect_spells_known(): void
    {
        $character = Character::factory()->create();

        // Create test spells
        $fireBolt = Spell::factory()->create(['name' => 'Fire Bolt', 'slug' => 'fire-bolt', 'slug' => 'test:fire-bolt', 'level' => 0]);
        $magicMissile = Spell::factory()->create(['name' => 'Magic Missile', 'slug' => 'magic-missile', 'slug' => 'test:magic-missile', 'level' => 1]);

        // Create cantrip choice
        $cantripChoice = new PendingChoice(
            id: 'spell|class|test:wizard|1|cantrips',
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
            metadata: ['spell_level' => 0, 'class_slug' => 'test:wizard'],
        );

        // Create spells known choice
        $spellChoice = new PendingChoice(
            id: 'spell|class|test:wizard|1|spells_known',
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
            metadata: ['spell_level' => 1, 'class_slug' => 'test:wizard'],
        );

        // Resolve both choices
        $this->handler->resolve($character, $cantripChoice, ['selected' => [$fireBolt->slug]]);
        $this->handler->resolve($character, $spellChoice, ['selected' => [$magicMissile->slug]]);
        $character->refresh();

        // Should have both spells (different choice groups: cantrips vs spells_known)
        expect($character->spells)->toHaveCount(2);
        $spellSlugs = $character->spells->pluck('spell_slug')->toArray();
        expect($spellSlugs)->toContain($fireBolt->slug)
            ->and($spellSlugs)->toContain($magicMissile->slug);
    }

    #[Test]
    public function resolving_level_2_spells_does_not_affect_level_1_spells(): void
    {
        $character = Character::factory()->create();

        // Create test spells
        $level1Spell = Spell::factory()->create(['name' => 'Magic Missile', 'slug' => 'magic-missile', 'slug' => 'test:magic-missile', 'level' => 1]);
        $level2Spell = Spell::factory()->create(['name' => 'Invisibility', 'slug' => 'invisibility', 'slug' => 'test:invisibility', 'level' => 2]);

        // Create level 1 spell choice
        $level1Choice = new PendingChoice(
            id: 'spell|class|test:bard|1|spells_known',
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
            metadata: ['spell_level' => 1, 'class_slug' => 'test:bard'],
        );

        // Create level 2 spell choice (different level_acquired in choice ID)
        $level2Choice = new PendingChoice(
            id: 'spell|class|test:bard|3|spells_known',  // Note: level 3 (when 2nd level spells become available)
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
            metadata: ['spell_level' => 2, 'class_slug' => 'test:bard'],
        );

        // Resolve level 1 spell choice
        $this->handler->resolve($character, $level1Choice, ['selected' => [$level1Spell->slug]]);
        $character->refresh();
        expect($character->spells)->toHaveCount(1);

        // Resolve level 2 (actually level 3) spell choice
        $this->handler->resolve($character, $level2Choice, ['selected' => [$level2Spell->slug]]);
        $character->refresh();

        // Should have BOTH spells (different level_acquired)
        expect($character->spells)->toHaveCount(2);
        $spellSlugs = $character->spells->pluck('spell_slug')->toArray();
        expect($spellSlugs)->toContain($level1Spell->slug)
            ->and($spellSlugs)->toContain($level2Spell->slug);
    }
}
