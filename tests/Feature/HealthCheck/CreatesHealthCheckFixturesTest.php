<?php

use App\Models\CharacterClass;
use App\Models\Monster;
use App\Models\Spell;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Traits\CreatesHealthCheckFixtures;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class, CreatesHealthCheckFixtures::class)
    ->beforeEach(function () {
        // LookupSeeder runs automatically via TestCase::$seeder
        $this->setUpHealthCheckFixtures();
    });

it('creates spell fixture', function () {
    expect($this->fixtures)->toHaveKey('spell');
    expect($this->fixtures['spell'])->toBeInstanceOf(Spell::class);
    expect($this->fixtures['spell']->slug)->not->toBeEmpty();
});

it('creates monster fixture with alignment', function () {
    expect($this->fixtures)->toHaveKey('monster');
    expect($this->fixtures['monster'])->toBeInstanceOf(Monster::class);
    expect($this->fixtures['monster']->alignment)->not->toBeEmpty();
});

it('creates class fixture', function () {
    expect($this->fixtures)->toHaveKey('class');
    expect($this->fixtures['class'])->toBeInstanceOf(CharacterClass::class);
});

it('provides lookup fixtures from seeder', function () {
    expect($this->fixtures)->toHaveKey('abilityScore');
    expect($this->fixtures)->toHaveKey('spellSchool');
    expect($this->fixtures)->toHaveKey('damageType');
});

it('substitutes path parameters correctly', function () {
    $path = '/v1/spells/{spell}';
    $params = ['spell'];

    $result = $this->substitutePathParams($path, $params);

    expect($result)->toBe('/v1/spells/'.$this->fixtures['spell']->slug);
});
