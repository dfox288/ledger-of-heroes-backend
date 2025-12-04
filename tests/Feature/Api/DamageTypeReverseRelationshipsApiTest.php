<?php

namespace Tests\Feature\Api;

use App\Models\DamageType;
use App\Models\Item;
use App\Models\Spell;
use App\Models\SpellEffect;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Api\Concerns\ReverseRelationshipTestCase;

#[\PHPUnit\Framework\Attributes\Group('feature-db')]
class DamageTypeReverseRelationshipsApiTest extends ReverseRelationshipTestCase
{
    protected $seeder = \Database\Seeders\LookupSeeder::class;

    protected function getDamageType(string $name): DamageType
    {
        return DamageType::firstOrCreate(['name' => $name], ['code' => substr($name, 0, 2)]);
    }

    // ========================================
    // Spells Endpoint Tests
    // ========================================

    #[Test]
    public function it_returns_spells_for_damage_type()
    {
        $fire = $this->getDamageType('Fire');

        $fireball = Spell::factory()->create(['name' => 'Fireball', 'slug' => 'fireball']);
        $burningHands = Spell::factory()->create(['name' => 'Burning Hands', 'slug' => 'burning-hands']);

        SpellEffect::factory()->create(['spell_id' => $fireball->id, 'damage_type_id' => $fire->id]);
        SpellEffect::factory()->create(['spell_id' => $burningHands->id, 'damage_type_id' => $fire->id]);

        // Ice storm uses different damage type - should not appear
        $cold = $this->getDamageType('Cold');
        $iceStorm = Spell::factory()->create(['name' => 'Ice Storm']);
        SpellEffect::factory()->create(['spell_id' => $iceStorm->id, 'damage_type_id' => $cold->id]);

        $this->assertReturnsRelatedEntities("/api/v1/lookups/damage-types/{$fire->id}/spells", 2, ['Burning Hands', 'Fireball']);
    }

    #[Test]
    public function it_returns_empty_when_damage_type_has_no_spells()
    {
        $radiant = $this->getDamageType('Radiant');

        $this->assertReturnsEmpty("/api/v1/lookups/damage-types/{$radiant->id}/spells");
    }

    #[Test]
    public function it_accepts_numeric_id_for_spells_endpoint()
    {
        $fire = $this->getDamageType('Fire');
        $spell = Spell::factory()->create();
        SpellEffect::factory()->create(['spell_id' => $spell->id, 'damage_type_id' => $fire->id]);

        $this->assertAcceptsAlternativeIdentifier("/api/v1/lookups/damage-types/{$fire->id}/spells");
    }

    #[Test]
    public function it_paginates_spell_results_for_damage_type()
    {
        $fire = $this->getDamageType('Fire');

        $this->createMultipleEntities(75, function () use ($fire) {
            $spell = Spell::factory()->create();
            SpellEffect::factory()->create(['spell_id' => $spell->id, 'damage_type_id' => $fire->id]);
            return $spell;
        });

        $this->assertPaginatesCorrectly("/api/v1/lookups/damage-types/{$fire->id}/spells?per_page=25", 25, 75, 25);
    }

    // ========================================
    // Items Endpoint Tests
    // ========================================

    #[Test]
    public function it_returns_items_for_damage_type()
    {
        $slashing = DamageType::firstOrCreate(['code' => 'S'], ['name' => 'Slashing']);

        $longsword = Item::factory()->create(['name' => 'Longsword', 'slug' => 'longsword', 'damage_type_id' => $slashing->id]);
        $greatsword = Item::factory()->create(['name' => 'Greatsword', 'slug' => 'greatsword', 'damage_type_id' => $slashing->id]);

        // Bludgeoning weapon - should not appear
        $bludgeoning = DamageType::firstOrCreate(['code' => 'B'], ['name' => 'Bludgeoning']);
        Item::factory()->create(['name' => 'Mace', 'damage_type_id' => $bludgeoning->id]);

        $this->assertReturnsRelatedEntities("/api/v1/lookups/damage-types/{$slashing->id}/items", 2, ['Greatsword', 'Longsword']);
    }

    #[Test]
    public function it_returns_empty_when_damage_type_has_no_items()
    {
        $psychic = $this->getDamageType('Psychic');

        $this->assertReturnsEmpty("/api/v1/lookups/damage-types/{$psychic->id}/items");
    }

    #[Test]
    public function it_accepts_numeric_id_for_items_endpoint()
    {
        $fire = $this->getDamageType('Fire');
        Item::factory()->create(['damage_type_id' => $fire->id]);

        $this->assertAcceptsAlternativeIdentifier("/api/v1/lookups/damage-types/{$fire->id}/items");
    }

    #[Test]
    public function it_paginates_item_results_for_damage_type()
    {
        $slashing = DamageType::firstOrCreate(['code' => 'S'], ['name' => 'Slashing']);

        $this->createMultipleEntities(75, fn() => Item::factory()->create(['damage_type_id' => $slashing->id]));

        $this->assertPaginatesCorrectly("/api/v1/lookups/damage-types/{$slashing->id}/items?per_page=25", 25, 75, 25);
    }
}
