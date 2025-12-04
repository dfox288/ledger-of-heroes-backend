<?php

namespace Tests\Feature\Api;

use App\Models\Spell;
use App\Models\SpellSchool;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Api\Concerns\ReverseRelationshipTestCase;

#[\PHPUnit\Framework\Attributes\Group('feature-db')]
class SpellSchoolReverseRelationshipsApiTest extends ReverseRelationshipTestCase
{
    protected $seeder = \Database\Seeders\LookupSeeder::class;

    #[Test]
    public function it_returns_spells_for_spell_school()
    {
        $evocation = SpellSchool::where('code', 'EV')->first();

        $fireball = Spell::factory()->create(['spell_school_id' => $evocation->id, 'name' => 'Fireball', 'slug' => 'fireball']);
        $magicMissile = Spell::factory()->create(['spell_school_id' => $evocation->id, 'name' => 'Magic Missile', 'slug' => 'magic-missile']);

        // Different school - should not appear
        $abjuration = SpellSchool::where('code', 'A')->first();
        Spell::factory()->create(['spell_school_id' => $abjuration->id, 'name' => 'Shield']);

        $this->assertReturnsRelatedEntities("/api/v1/lookups/spell-schools/{$evocation->id}/spells", 2, ['Fireball', 'Magic Missile']);
    }

    #[Test]
    public function it_returns_empty_when_school_has_no_spells()
    {
        $school = SpellSchool::where('code', 'D')->first();

        $this->assertReturnsEmpty("/api/v1/lookups/spell-schools/{$school->id}/spells");
    }

    #[Test]
    public function it_accepts_numeric_id_for_spells_endpoint()
    {
        $school = SpellSchool::where('code', 'EV')->first();
        Spell::factory()->create(['spell_school_id' => $school->id]);

        $this->assertAcceptsAlternativeIdentifier("/api/v1/lookups/spell-schools/{$school->id}/spells");
    }

    #[Test]
    public function it_paginates_spell_results()
    {
        $school = SpellSchool::where('code', 'EV')->first();

        $this->createMultipleEntities(75, fn() => Spell::factory()->create(['spell_school_id' => $school->id]));

        $this->assertPaginatesCorrectly("/api/v1/lookups/spell-schools/{$school->id}/spells?per_page=25", 25, 75, 25);
    }
}
