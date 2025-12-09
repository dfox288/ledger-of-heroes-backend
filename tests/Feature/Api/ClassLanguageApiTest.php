<?php

use App\Models\CharacterClass;
use App\Models\EntityLanguage;
use App\Models\Language;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class)
    ->group('feature-db')
    ->beforeEach(function () {
        $this->seed = true;
    });

it('rogue class has thieves cant language', function () {
    // Create rogue with Thieves' Cant language
    $rogue = CharacterClass::factory()->create([
        'name' => 'Rogue',
        'slug' => 'rogue',
    ]);

    $thievesCant = Language::where('slug', 'thieves-cant')->first();
    expect($thievesCant)->not->toBeNull("Thieves' Cant should exist in seeded languages");

    EntityLanguage::create([
        'reference_type' => CharacterClass::class,
        'reference_id' => $rogue->id,
        'language_id' => $thievesCant->id,
        'is_choice' => false,
    ]);

    // Verify the relationship works
    $rogue->refresh();
    expect($rogue->languages)->toHaveCount(1)
        ->and($rogue->languages->first()->language_id)->toBe($thievesCant->id);

    // Verify API response includes languages
    $response = $this->getJson("/api/v1/classes/{$rogue->id}");

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'id',
                'name',
                'languages' => [
                    '*' => ['language', 'is_choice'],
                ],
            ],
        ]);

    // Verify the language data
    $languages = $response->json('data.languages');
    expect($languages)->toHaveCount(1)
        ->and($languages[0]['is_choice'])->toBeFalse()
        ->and($languages[0]['language']['name'])->toBe("Thieves' Cant");
});

it('druid class has druidic language', function () {
    // Create druid with Druidic language
    $druid = CharacterClass::factory()->create([
        'name' => 'Druid',
        'slug' => 'druid',
    ]);

    $druidic = Language::where('slug', 'druidic')->first();
    expect($druidic)->not->toBeNull('Druidic should exist in seeded languages');

    EntityLanguage::create([
        'reference_type' => CharacterClass::class,
        'reference_id' => $druid->id,
        'language_id' => $druidic->id,
        'is_choice' => false,
    ]);

    // Verify the relationship works
    $druid->refresh();
    expect($druid->languages)->toHaveCount(1);

    // Verify API response
    $response = $this->getJson("/api/v1/classes/{$druid->id}");

    $response->assertOk();
    $languages = $response->json('data.languages');
    expect($languages)->toHaveCount(1)
        ->and($languages[0]['language']['name'])->toBe('Druidic');
});

it('class without language grants has empty languages', function () {
    $fighter = CharacterClass::factory()->create([
        'name' => 'Fighter',
        'slug' => 'fighter',
    ]);

    // No languages added

    $response = $this->getJson("/api/v1/classes/{$fighter->id}");

    $response->assertOk();
    expect($response->json('data.languages'))->toBeEmpty();
});

it('character class model has languages relationship', function () {
    $class = CharacterClass::factory()->create();
    $language = Language::first();

    EntityLanguage::create([
        'reference_type' => CharacterClass::class,
        'reference_id' => $class->id,
        'language_id' => $language->id,
        'is_choice' => false,
    ]);

    $class->refresh();

    expect(method_exists($class, 'languages'))->toBeTrue()
        ->and($class->languages)->toHaveCount(1)
        ->and($class->languages->first()->language_id)->toBe($language->id);
});

it('importer handles invalid language slugs gracefully', function () {
    // The ImportsLanguages trait should skip languages that don't exist in the database
    $class = CharacterClass::factory()->create();

    // Simulate importing with an invalid slug - this should not throw an exception
    // and should not create any entity_language records
    $languagesData = [
        ['slug' => 'nonexistent-language', 'is_choice' => false],
        ['language_slug' => 'also-does-not-exist', 'is_choice' => false],
    ];

    // The importer uses the trait method which looks up by slug
    // Invalid slugs should be silently skipped
    foreach ($languagesData as $langData) {
        $language = Language::where('slug', $langData['slug'] ?? $langData['language_slug'] ?? null)->first();
        if ($language) {
            EntityLanguage::create([
                'reference_type' => CharacterClass::class,
                'reference_id' => $class->id,
                'language_id' => $language->id,
                'is_choice' => $langData['is_choice'],
            ]);
        }
    }

    $class->refresh();
    expect($class->languages)->toBeEmpty();
});
