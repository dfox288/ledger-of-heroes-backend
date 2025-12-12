<?php

use App\Models\CreatureType;
use App\Models\Monster;
use App\Services\Importers\MonsterImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class)->group('unit-db');

it('belongs to a creature type', function () {
    $creatureType = CreatureType::factory()->undead()->create();
    $monster = Monster::factory()->create(['creature_type_id' => $creatureType->id]);

    expect($monster->creatureType)->toBeInstanceOf(CreatureType::class);
    expect($monster->creatureType->slug)->toBe('core:undead');
});

it('can filter monsters by creature type', function () {
    $undead = CreatureType::factory()->undead()->create();
    $construct = CreatureType::factory()->construct()->create();

    Monster::factory()->count(3)->create(['creature_type_id' => $undead->id]);
    Monster::factory()->count(2)->create(['creature_type_id' => $construct->id]);

    expect(Monster::where('creature_type_id', $undead->id)->count())->toBe(3);
    expect(Monster::where('creature_type_id', $construct->id)->count())->toBe(2);
});

describe('creature type extraction', function () {
    beforeEach(function () {
        $this->importer = new MonsterImporter;
        $this->extractMethod = new ReflectionMethod($this->importer, 'extractBaseCreatureType');
        $this->extractMethod->setAccessible(true);
    });

    it('extracts base type from simple string', function () {
        expect($this->extractMethod->invoke($this->importer, 'humanoid'))->toBe('humanoid');
        expect($this->extractMethod->invoke($this->importer, 'dragon'))->toBe('dragon');
        expect($this->extractMethod->invoke($this->importer, 'undead'))->toBe('undead');
    });

    it('extracts base type before parentheses', function () {
        expect($this->extractMethod->invoke($this->importer, 'humanoid (elf)'))->toBe('humanoid');
        expect($this->extractMethod->invoke($this->importer, 'fiend (demon)'))->toBe('fiend');
        expect($this->extractMethod->invoke($this->importer, 'fiend (demon, shapechanger)'))->toBe('fiend');
    });

    it('normalizes case to lowercase', function () {
        expect($this->extractMethod->invoke($this->importer, 'Humanoid'))->toBe('humanoid');
        expect($this->extractMethod->invoke($this->importer, 'DRAGON'))->toBe('dragon');
        expect($this->extractMethod->invoke($this->importer, 'Humanoid (Elf)'))->toBe('humanoid');
    });

    it('handles whitespace correctly', function () {
        expect($this->extractMethod->invoke($this->importer, '  humanoid  '))->toBe('humanoid');
        expect($this->extractMethod->invoke($this->importer, 'humanoid  (elf)'))->toBe('humanoid');
    });

    it('extracts swarm as separate creature type', function () {
        expect($this->extractMethod->invoke($this->importer, 'swarm of tiny beasts'))->toBe('swarm');
        expect($this->extractMethod->invoke($this->importer, 'swarm of medium beasts'))->toBe('swarm');
        expect($this->extractMethod->invoke($this->importer, 'Swarm of Bats'))->toBe('swarm');
    });
});
