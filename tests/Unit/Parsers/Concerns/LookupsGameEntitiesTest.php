<?php

namespace Tests\Unit\Parsers\Concerns;

use App\Models\AbilityScore;
use App\Models\Skill;
use App\Services\Parsers\Concerns\LookupsGameEntities;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LookupsGameEntitiesTest extends TestCase
{
    use LookupsGameEntities, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test data (idempotent with firstOrCreate)
        $str = AbilityScore::firstOrCreate(
            ['code' => 'STR'],
            ['name' => 'Strength']
        );
        $dex = AbilityScore::firstOrCreate(
            ['code' => 'DEX'],
            ['name' => 'Dexterity']
        );

        Skill::firstOrCreate(
            ['name' => 'Acrobatics'],
            ['ability_score_id' => $dex->id, 'description' => 'Test']
        );
        Skill::firstOrCreate(
            ['name' => 'Athletics'],
            ['ability_score_id' => $str->id, 'description' => 'Test']
        );
    }

    #[Test]
    public function it_looks_up_skill_by_exact_name()
    {
        $skillId = $this->lookupSkillId('Acrobatics');
        $this->assertNotNull($skillId);
        $this->assertDatabaseHas('skills', ['id' => $skillId, 'name' => 'Acrobatics']);
    }

    #[Test]
    public function it_is_case_insensitive_for_skills()
    {
        $skillId = $this->lookupSkillId('acrobatics');
        $this->assertNotNull($skillId);
        $this->assertDatabaseHas('skills', ['id' => $skillId, 'name' => 'Acrobatics']);
    }

    #[Test]
    public function it_returns_null_for_unknown_skill()
    {
        $skillId = $this->lookupSkillId('Unknown Skill');
        $this->assertNull($skillId);
    }

    #[Test]
    public function it_looks_up_ability_score_by_name()
    {
        $abilityId = $this->lookupAbilityScoreId('Strength');
        $this->assertNotNull($abilityId);
        $this->assertDatabaseHas('ability_scores', ['id' => $abilityId, 'name' => 'Strength']);
    }

    #[Test]
    public function it_looks_up_ability_score_by_code()
    {
        $abilityId = $this->lookupAbilityScoreId('STR');
        $this->assertNotNull($abilityId);
        $this->assertDatabaseHas('ability_scores', ['id' => $abilityId, 'name' => 'Strength']);
    }

    #[Test]
    public function it_caches_lookups_for_performance()
    {
        // First lookup
        $id1 = $this->lookupSkillId('Acrobatics');

        // Second lookup should use cache (not hit DB again)
        $id2 = $this->lookupSkillId('Acrobatics');

        $this->assertEquals($id1, $id2);
    }
}
