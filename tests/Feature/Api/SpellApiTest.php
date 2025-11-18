<?php

namespace Tests\Feature\Api;

use App\Models\CharacterClass;
use App\Models\Spell;
use App\Models\SpellEffect;
use App\Models\SpellSchool;
use App\Models\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpellApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_get_all_spells(): void
    {
        $school = SpellSchool::first();
        $source = Source::first();

        $spell = Spell::create([
            'name' => 'Fireball',
            'level' => 3,
            'spell_school_id' => $school->id,
            'casting_time' => '1 action',
            'range' => '150 feet',
            'components' => 'V, S, M',
            'duration' => 'Instantaneous',
            'needs_concentration' => false,
            'is_ritual' => false,
            'description' => 'A bright streak flashes from your pointing finger...',
        ]);

        $spell->sources()->create([
            'source_id' => $source->id,
            'pages' => '241',
        ]);

        $response = $this->getJson('/api/v1/spells');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'level',
                        'school' => ['id', 'name', 'code'],
                        'casting_time',
                        'range',
                        'components',
                        'duration',
                        'needs_concentration',
                        'is_ritual',
                        'description',
                        'sources' => [
                            '*' => ['code', 'name', 'pages'],
                        ],
                    ]
                ],
                'links',
                'meta',
            ])
            ->assertJsonCount(1, 'data');
    }

    public function test_can_search_spells(): void
    {
        $school = SpellSchool::first();
        $source = Source::first();

        $spell1 = Spell::create([
            'name' => 'Fireball',
            'level' => 3,
            'spell_school_id' => $school->id,
            'casting_time' => '1 action',
            'range' => '150 feet',
            'components' => 'V, S, M',
            'duration' => 'Instantaneous',
            'needs_concentration' => false,
            'is_ritual' => false,
            'description' => 'A bright streak flashes from your pointing finger...',
        ]);

        $spell1->sources()->create([
            'source_id' => $source->id,
            'pages' => '241',
        ]);

        $spell2 = Spell::create([
            'name' => 'Ice Storm',
            'level' => 4,
            'spell_school_id' => $school->id,
            'casting_time' => '1 action',
            'range' => '300 feet',
            'components' => 'V, S, M',
            'duration' => 'Instantaneous',
            'needs_concentration' => false,
            'is_ritual' => false,
            'description' => 'A hail of rock-hard ice pounds to the ground...',
        ]);

        $spell2->sources()->create([
            'source_id' => $source->id,
            'pages' => '252',
        ]);

        $response = $this->getJson('/api/v1/spells?search=fireball');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Fireball');
    }

    public function test_spell_includes_effects_in_response(): void
    {
        $school = SpellSchool::first();
        $source = Source::first();

        $spell = Spell::create([
            'name' => 'Magic Missile',
            'level' => 1,
            'spell_school_id' => $school->id,
            'casting_time' => '1 action',
            'range' => '120 feet',
            'components' => 'V, S',
            'duration' => 'Instantaneous',
            'needs_concentration' => false,
            'is_ritual' => false,
            'description' => 'You create three glowing darts of magical force.',
        ]);

        $spell->sources()->create([
            'source_id' => $source->id,
            'pages' => '257',
        ]);

        // Create spell effects
        SpellEffect::create([
            'spell_id' => $spell->id,
            'effect_type' => 'damage',
            'description' => 'Force damage per dart',
            'dice_formula' => '1d4+1',
            'base_value' => null,
            'scaling_type' => 'spell_slot',
            'min_character_level' => null,
            'min_spell_slot' => 2,
            'scaling_increment' => '1d4+1',
        ]);

        SpellEffect::create([
            'spell_id' => $spell->id,
            'effect_type' => 'scaling',
            'description' => 'Additional darts per spell level',
            'dice_formula' => null,
            'base_value' => 1,
            'scaling_type' => 'spell_slot',
            'min_character_level' => null,
            'min_spell_slot' => 2,
            'scaling_increment' => null,
        ]);

        // Test the show endpoint
        $response = $this->getJson("/api/v1/spells/{$spell->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'effects' => [
                        '*' => [
                            'id',
                            'effect_type',
                            'description',
                            'dice_formula',
                            'base_value',
                            'scaling_type',
                            'min_character_level',
                            'min_spell_slot',
                            'scaling_increment',
                        ]
                    ]
                ]
            ])
            ->assertJsonCount(2, 'data.effects')
            ->assertJsonPath('data.effects.0.effect_type', 'damage')
            ->assertJsonPath('data.effects.0.dice_formula', '1d4+1')
            ->assertJsonPath('data.effects.1.effect_type', 'scaling');
    }

    public function test_spell_includes_classes_in_response(): void
    {
        $school = SpellSchool::first();
        $source = Source::first();

        // Create classes
        $wizard = CharacterClass::create([
            'name' => 'Wizard',
            'hit_die' => 6,
            'description' => 'A scholarly magic-user capable of manipulating the structures of reality.',
        ]);

        $wizard->sources()->create([
            'source_id' => $source->id,
            'pages' => '112',
        ]);

        $sorcerer = CharacterClass::create([
            'name' => 'Sorcerer',
            'hit_die' => 6,
            'description' => 'A spellcaster who draws on inherent magic from a gift or bloodline.',
        ]);

        $sorcerer->sources()->create([
            'source_id' => $source->id,
            'pages' => '99',
        ]);

        // Create a spell
        $spell = Spell::create([
            'name' => 'Fireball',
            'level' => 3,
            'spell_school_id' => $school->id,
            'casting_time' => '1 action',
            'range' => '150 feet',
            'components' => 'V, S, M',
            'duration' => 'Instantaneous',
            'needs_concentration' => false,
            'is_ritual' => false,
            'description' => 'A bright streak flashes from your pointing finger...',
        ]);

        $spell->sources()->create([
            'source_id' => $source->id,
            'pages' => '241',
        ]);

        // Associate spell with classes
        $spell->classes()->attach([$wizard->id, $sorcerer->id]);

        // Test the show endpoint
        $response = $this->getJson("/api/v1/spells/{$spell->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'classes' => [
                        '*' => [
                            'id',
                            'name',
                            'hit_die',
                            'description',
                        ]
                    ]
                ]
            ])
            ->assertJsonCount(2, 'data.classes')
            ->assertJsonPath('data.classes.0.name', 'Wizard')
            ->assertJsonPath('data.classes.0.hit_die', 6)
            ->assertJsonPath('data.classes.1.name', 'Sorcerer')
            ->assertJsonPath('data.classes.1.hit_die', 6);
    }

    /** @test */
    public function test_spell_includes_spell_school_resource()
    {
        $school = SpellSchool::first();
        $source = Source::first();

        $spell = Spell::create([
            'name' => 'Test Spell',
            'level' => 1,
            'spell_school_id' => $school->id,
            'casting_time' => '1 action',
            'range' => 'Self',
            'components' => 'V',
            'duration' => 'Instantaneous',
            'needs_concentration' => false,
            'is_ritual' => false,
            'description' => 'Test',
        ]);

        $response = $this->getJson("/api/v1/spells/{$spell->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'school' => [
                        'id',
                        'code',
                        'name',
                    ],
                ],
            ])
            ->assertJson([
                'data' => [
                    'school' => [
                        'id' => $school->id,
                        'code' => $school->code,
                        'name' => $school->name,
                    ],
                ],
            ]);
    }
}
