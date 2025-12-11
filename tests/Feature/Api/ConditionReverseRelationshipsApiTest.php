<?php

namespace Tests\Feature\Api;

use App\Models\Condition;
use App\Models\Monster;
use App\Models\Spell;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Api\Concerns\ReverseRelationshipTestCase;

#[\PHPUnit\Framework\Attributes\Group('feature-db')]
class ConditionReverseRelationshipsApiTest extends ReverseRelationshipTestCase
{
    // ========================================
    // Spells Endpoint Tests
    // ========================================

    #[Test]
    public function it_returns_spells_for_condition()
    {
        $poisoned = Condition::where('slug', 'core:poisoned')->first();

        $poisonSpray = Spell::factory()->create(['name' => 'Poison Spray', 'slug' => 'poison-spray']);
        $cloudkill = Spell::factory()->create(['name' => 'Cloudkill', 'slug' => 'cloudkill']);

        $poisoned->spells()->attach($poisonSpray, ['effect_type' => 'inflicts', 'description' => 'Target becomes poisoned']);
        $poisoned->spells()->attach($cloudkill, ['effect_type' => 'inflicts', 'description' => 'Creatures in area become poisoned']);

        $this->assertReturnsRelatedEntities('/api/v1/lookups/conditions/core:poisoned/spells', 2, ['Cloudkill', 'Poison Spray']);
    }

    #[Test]
    public function it_returns_empty_when_condition_has_no_spells()
    {
        $custom = Condition::factory()->create(['slug' => 'custom-condition']);

        $this->assertReturnsEmpty('/api/v1/lookups/conditions/custom-condition/spells');
    }

    #[Test]
    public function it_accepts_numeric_id_for_spells_endpoint()
    {
        $condition = Condition::factory()->create();
        $spell = Spell::factory()->create();
        $condition->spells()->attach($spell, ['effect_type' => 'inflicts']);

        $this->assertAcceptsAlternativeIdentifier("/api/v1/lookups/conditions/{$condition->id}/spells");
    }

    #[Test]
    public function it_paginates_spell_results_for_condition()
    {
        $stunned = Condition::where('slug', 'core:stunned')->first();

        $this->createMultipleEntities(75, function () use ($stunned) {
            $spell = Spell::factory()->create();
            $stunned->spells()->attach($spell, ['effect_type' => 'inflicts']);

            return $spell;
        });

        $this->assertPaginatesCorrectly('/api/v1/lookups/conditions/core:stunned/spells?per_page=25', 25, 75, 25);
    }

    // ========================================
    // Monsters Endpoint Tests
    // ========================================

    #[Test]
    public function it_returns_monsters_for_condition()
    {
        $frightened = Condition::where('slug', 'core:frightened')->first();

        $dragon = Monster::factory()->create(['name' => 'Adult Red Dragon', 'slug' => 'adult-red-dragon']);
        $beholder = Monster::factory()->create(['name' => 'Beholder', 'slug' => 'beholder']);

        $frightened->monsters()->attach($dragon, ['effect_type' => 'inflicts', 'description' => 'Frightful Presence']);
        $frightened->monsters()->attach($beholder, ['effect_type' => 'inflicts', 'description' => 'Fear Ray']);

        $this->assertReturnsRelatedEntities('/api/v1/lookups/conditions/core:frightened/monsters', 2, ['Adult Red Dragon', 'Beholder']);
    }

    #[Test]
    public function it_returns_empty_when_condition_has_no_monsters()
    {
        $custom = Condition::factory()->create(['slug' => 'custom-condition']);

        $this->assertReturnsEmpty('/api/v1/lookups/conditions/custom-condition/monsters');
    }

    #[Test]
    public function it_accepts_numeric_id_for_monsters_endpoint()
    {
        $condition = Condition::factory()->create();
        $monster = Monster::factory()->create();
        $condition->monsters()->attach($monster, ['effect_type' => 'inflicts']);

        $this->assertAcceptsAlternativeIdentifier("/api/v1/lookups/conditions/{$condition->id}/monsters");
    }

    #[Test]
    public function it_paginates_monster_results_for_condition()
    {
        $paralyzed = Condition::where('slug', 'core:paralyzed')->first();

        $this->createMultipleEntities(75, function () use ($paralyzed) {
            $monster = Monster::factory()->create();
            $paralyzed->monsters()->attach($monster, ['effect_type' => 'inflicts']);

            return $monster;
        });

        $this->assertPaginatesCorrectly('/api/v1/lookups/conditions/core:paralyzed/monsters?per_page=25', 25, 75, 25);
    }
}
