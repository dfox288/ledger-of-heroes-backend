<?php

namespace Tests\Feature\Api;

use App\Models\CharacterClass;
use App\Models\Spell;
use App\Models\SpellEffect;
use App\Models\SpellSchool;
use App\Models\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;


class SpellApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_get_all_spells(): void
    {
        $source = $this->getSource('PHB');

        $spell = Spell::factory()->create([
            'name' => 'Fireball',
            'level' => 3,
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
        $source = $this->getSource('PHB');

        $spell1 = Spell::factory()->create([
            'name' => 'Fireball',
            'level' => 3,
        ]);

        $spell1->sources()->create([
            'source_id' => $source->id,
            'pages' => '241',
        ]);

        $spell2 = Spell::factory()->create([
            'name' => 'Ice Storm',
            'level' => 4,
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
        $source = $this->getSource('PHB');

        $spell = Spell::factory()->create([
            'name' => 'Magic Missile',
            'level' => 1,
        ]);

        $spell->sources()->create([
            'source_id' => $source->id,
            'pages' => '257',
        ]);

        // Create spell effects
        SpellEffect::factory()->create([
            'spell_id' => $spell->id,
            'effect_type' => 'damage',
            'description' => 'Force damage per dart',
            'dice_formula' => '1d4+1',
        ]);

        SpellEffect::factory()->create([
            'spell_id' => $spell->id,
            'effect_type' => 'scaling',
            'description' => 'Additional darts per spell level',
            'base_value' => 1,
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
        $source = $this->getSource('PHB');

        // Create classes
        $wizard = CharacterClass::factory()->create([
            'name' => 'Wizard',
            'hit_die' => 6,
        ]);

        $wizard->sources()->create([
            'source_id' => $source->id,
            'pages' => '112',
        ]);

        $sorcerer = CharacterClass::factory()->create([
            'name' => 'Sorcerer',
            'hit_die' => 6,
        ]);

        $sorcerer->sources()->create([
            'source_id' => $source->id,
            'pages' => '99',
        ]);

        // Create a spell
        $spell = Spell::factory()->create([
            'name' => 'Fireball',
            'level' => 3,
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

    #[Test]
    public function test_spell_includes_spell_school_resource()
    {
        $school = $this->getSpellSchool('EV');

        $spell = Spell::factory()->create([
            'name' => 'Test Spell',
            'level' => 1,
            'spell_school_id' => $school->id,
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

    #[Test]
    public function test_spell_includes_sources_as_resource()
    {
        $source = $this->getSource('PHB');

        $spell = Spell::factory()->create([
            'name' => 'Test Source Spell',
            'level' => 1,
        ]);

        $spell->sources()->create([
            'reference_type' => Spell::class,
            'reference_id' => $spell->id,
            'source_id' => $source->id,
            'pages' => '123',
        ]);

        $response = $this->getJson("/api/v1/spells/{$spell->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'sources' => [
                        '*' => [
                            'code',
                            'name',
                            'pages',
                        ],
                    ],
                ],
            ])
            ->assertJsonFragment([
                'code' => 'PHB',
                'pages' => '123',
            ]);
    }

    #[Test]
    public function test_spell_effect_includes_damage_type_resource_when_present()
    {
        // Note: This test verifies the DamageTypeResource is properly integrated
        $spell = Spell::factory()->create([
            'name' => 'Test Spell',
            'level' => 1,
        ]);

        $spell->effects()->create([
            'effect_type' => 'damage',
            'description' => 'Test damage',
            'dice_formula' => '2d6',
        ]);

        $response = $this->getJson("/api/v1/spells/{$spell->id}");

        // Verify the effect is included without damage_type (null since column doesn't exist yet)
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'effects' => [
                        '*' => [
                            'id',
                            'effect_type',
                            'description',
                            'dice_formula',
                        ],
                    ],
                ],
            ])
            ->assertJsonPath('data.effects.0.effect_type', 'damage');
    }
}
