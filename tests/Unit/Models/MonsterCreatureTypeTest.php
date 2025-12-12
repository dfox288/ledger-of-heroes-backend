<?php

use App\Models\CreatureType;
use App\Models\Monster;
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
