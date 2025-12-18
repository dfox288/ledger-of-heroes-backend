<?php

namespace Tests\Feature\Api;

use App\Models\Background;
use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\Race;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests for the lightweight CharacterListResource used in index endpoint.
 *
 * @see https://github.com/dfox288/ledger-of-heroes/issues/721
 */
class CharacterListResourceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function character_list_returns_only_essential_fields(): void
    {
        $race = Race::factory()->create(['name' => 'Elf']);
        $class = CharacterClass::factory()->create(['name' => 'Wizard']);

        Character::factory()
            ->withRace($race)
            ->withClass($class)
            ->withAbilityScores([
                'strength' => 8,
                'dexterity' => 14,
                'constitution' => 12,
                'intelligence' => 16,
                'wisdom' => 10,
                'charisma' => 10,
            ])
            ->create(['name' => 'Gandalf']);

        $response = $this->getJson('/api/v1/characters');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'public_id',
                        'name',
                        'level',
                        'race',
                        'class_name',
                        'classes',
                        'portrait',
                        'is_complete',
                        'updated_at',
                    ],
                ],
            ])
            ->assertJsonPath('data.0.name', 'Gandalf')
            ->assertJsonPath('data.0.race.name', 'Elf')
            ->assertJsonPath('data.0.class_name', 'Wizard');
    }

    #[Test]
    public function character_list_does_not_include_heavy_fields(): void
    {
        Character::factory()
            ->withAbilityScores([
                'strength' => 18,
                'dexterity' => 14,
                'constitution' => 16,
                'intelligence' => 10,
                'wisdom' => 12,
                'charisma' => 8,
            ])
            ->create();

        $response = $this->getJson('/api/v1/characters');

        $response->assertOk();

        $data = $response->json('data.0');

        // These heavy fields should NOT be in the list response
        // Note: 'classes' IS included but as a lightweight array (name, level, is_primary only)
        $heavyFields = [
            'ability_scores',
            'base_ability_scores',
            'modifiers',
            'proficiency_bonus',
            'ability_score_method',
            'max_hit_points',
            'current_hit_points',
            'temp_hit_points',
            'death_save_successes',
            'death_save_failures',
            'is_dead',
            'armor_class',
            'currency',
            'spell_slots',
            'counters',
            'equipped',
            'proficiency_penalties',
            'attunement_slots',
            'speeds',
            'senses',
            'validation_status',
            'conditions',
            'optional_features',
            'feature_selections',
        ];

        foreach ($heavyFields as $field) {
            $this->assertArrayNotHasKey($field, $data, "Field '$field' should not be in list response");
        }
    }

    #[Test]
    public function character_list_handles_null_race_and_class(): void
    {
        Character::factory()->create([
            'name' => 'Draft Character',
            'race_slug' => null,
        ]);

        $response = $this->getJson('/api/v1/characters');

        $response->assertOk()
            ->assertJsonPath('data.0.name', 'Draft Character')
            ->assertJsonPath('data.0.race', null)
            ->assertJsonPath('data.0.class_name', null);
    }

    #[Test]
    public function character_list_shows_primary_class_for_multiclass(): void
    {
        $fighter = CharacterClass::factory()->create(['name' => 'Fighter']);
        $wizard = CharacterClass::factory()->create(['name' => 'Wizard']);

        $character = Character::factory()->create();

        // Add Fighter as primary (order 1), then Wizard
        $character->characterClasses()->create([
            'class_slug' => $fighter->slug,
            'level' => 5,
            'is_primary' => true,
            'order' => 1,
        ]);
        $character->characterClasses()->create([
            'class_slug' => $wizard->slug,
            'level' => 3,
            'is_primary' => false,
            'order' => 2,
        ]);

        $response = $this->getJson('/api/v1/characters');

        $response->assertOk()
            ->assertJsonPath('data.0.level', 8) // 5 + 3
            ->assertJsonPath('data.0.class_name', 'Fighter'); // Primary class only
    }

    #[Test]
    public function character_list_includes_lightweight_classes_array(): void
    {
        $fighter = CharacterClass::factory()->create(['name' => 'Fighter']);
        $wizard = CharacterClass::factory()->create(['name' => 'Wizard']);

        $character = Character::factory()->create();

        // Add Fighter as primary (order 1), then Wizard
        $character->characterClasses()->create([
            'class_slug' => $fighter->slug,
            'level' => 5,
            'is_primary' => true,
            'order' => 1,
        ]);
        $character->characterClasses()->create([
            'class_slug' => $wizard->slug,
            'level' => 3,
            'is_primary' => false,
            'order' => 2,
        ]);

        $response = $this->getJson('/api/v1/characters');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'classes' => [
                            '*' => ['name', 'level', 'is_primary'],
                        ],
                    ],
                ],
            ]);

        // Verify classes array content
        $classes = $response->json('data.0.classes');
        $this->assertCount(2, $classes);

        // Classes should be in order (primary first by order column)
        $this->assertEquals('Fighter', $classes[0]['name']);
        $this->assertEquals(5, $classes[0]['level']);
        $this->assertTrue($classes[0]['is_primary']);

        $this->assertEquals('Wizard', $classes[1]['name']);
        $this->assertEquals(3, $classes[1]['level']);
        $this->assertFalse($classes[1]['is_primary']);

        // Should NOT have heavy fields like hit_dice, subclass, slug
        $this->assertArrayNotHasKey('hit_dice', $classes[0]);
        $this->assertArrayNotHasKey('subclass', $classes[0]);
        $this->assertArrayNotHasKey('slug', $classes[0]);
        $this->assertArrayNotHasKey('class', $classes[0]); // No nested class object
    }

    #[Test]
    public function character_list_has_minimal_queries(): void
    {
        // Create 5 complete characters with race and class
        $race = Race::factory()->create();
        $class = CharacterClass::factory()->create();

        Character::factory()
            ->count(5)
            ->withRace($race)
            ->withClass($class)
            ->create();

        DB::enableQueryLog();

        $response = $this->getJson('/api/v1/characters');
        $response->assertOk();

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // Should have bounded queries regardless of character count:
        // 1 for characters, 1 for races, 1 for character_classes pivot,
        // 1 for classes, 1 for media, 1 for pagination count
        // NOT 5+ queries (which would indicate N+1)
        $this->assertLessThanOrEqual(
            8,
            count($queries),
            'Query count should be bounded (no N+1). Got '.count($queries).' queries: '.
            implode("\n", array_map(fn ($q) => $q['query'], $queries))
        );
    }

    #[Test]
    public function character_list_payload_is_significantly_smaller(): void
    {
        // Create a complete character with all the trimmings
        $race = Race::factory()->create();
        $class = CharacterClass::factory()->create();
        $background = Background::factory()->create();

        Character::factory()
            ->withRace($race)
            ->withClass($class)
            ->withBackground($background)
            ->withAbilityScores([
                'strength' => 16,
                'dexterity' => 14,
                'constitution' => 14,
                'intelligence' => 10,
                'wisdom' => 12,
                'charisma' => 8,
            ])
            ->create();

        $listResponse = $this->getJson('/api/v1/characters');
        $listResponse->assertOk();

        $listPayload = json_encode($listResponse->json('data.0'));
        $listSize = strlen($listPayload);

        // List response should be under 500 bytes per character
        // (vs ~2200 bytes with the full resource)
        $this->assertLessThan(
            500,
            $listSize,
            "List payload is {$listSize} bytes, should be under 500"
        );
    }
}
