<?php

namespace Tests\Unit\Parsers;

use App\Models\AbilityScore;
use App\Models\ProficiencyType;
use App\Models\Race;
use App\Models\Skill;
use App\Services\Parsers\FeatXmlParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FeatXmlParserPrerequisitesTest extends TestCase
{
    use RefreshDatabase;

    private FeatXmlParser $parser;

    protected $seed = true; // Auto-seed ability_scores, proficiency_types, races

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new FeatXmlParser;
    }

    #[Test]
    public function it_parses_single_ability_score_prerequisite()
    {
        // "Dexterity 13 or higher"
        $result = $this->parser->parsePrerequisites('Dexterity 13 or higher');

        $this->assertCount(1, $result);
        $this->assertEquals(AbilityScore::class, $result[0]['prerequisite_type']);
        $this->assertNotNull($result[0]['prerequisite_id']); // Should be DEX id
        $this->assertEquals(13, $result[0]['minimum_value']);
        $this->assertEquals(1, $result[0]['group_id']);
    }

    #[Test]
    public function it_parses_dual_ability_score_prerequisite()
    {
        // "Intelligence or Wisdom 13 or higher" (OR between two abilities)
        $result = $this->parser->parsePrerequisites('Intelligence or Wisdom 13 or higher');

        $this->assertCount(2, $result);

        // Both should be in same group (OR logic)
        $this->assertEquals(1, $result[0]['group_id']);
        $this->assertEquals(1, $result[1]['group_id']);

        // Both should have minimum value
        $this->assertEquals(13, $result[0]['minimum_value']);
        $this->assertEquals(13, $result[1]['minimum_value']);

        // Should be ability scores
        $this->assertEquals(AbilityScore::class, $result[0]['prerequisite_type']);
        $this->assertEquals(AbilityScore::class, $result[1]['prerequisite_type']);
    }

    #[Test]
    public function it_parses_strength_prerequisite()
    {
        // "Strength 13 or higher"
        $result = $this->parser->parsePrerequisites('Strength 13 or higher');

        $this->assertCount(1, $result);

        $abilityScore = AbilityScore::where('code', 'STR')->first();
        $this->assertNotNull($abilityScore);

        $this->assertEquals(AbilityScore::class, $result[0]['prerequisite_type']);
        $this->assertEquals($abilityScore->id, $result[0]['prerequisite_id']);
        $this->assertEquals(13, $result[0]['minimum_value']);
    }

    #[Test]
    public function it_parses_single_race_prerequisite()
    {
        // "Elf"
        Race::factory()->create(['name' => 'Elf', 'slug' => 'elf']);

        $result = $this->parser->parsePrerequisites('Elf');

        $this->assertCount(1, $result);
        $this->assertEquals(Race::class, $result[0]['prerequisite_type']);
        $this->assertNotNull($result[0]['prerequisite_id']);
        $this->assertEquals(1, $result[0]['group_id']);
    }

    #[Test]
    public function it_parses_multiple_races_prerequisite()
    {
        // "Dwarf, Gnome, Halfling" (OR between races)
        Race::factory()->create(['name' => 'Dwarf', 'slug' => 'dwarf']);
        Race::factory()->create(['name' => 'Gnome', 'slug' => 'gnome']);
        Race::factory()->create(['name' => 'Halfling', 'slug' => 'halfling']);

        $result = $this->parser->parsePrerequisites('Dwarf, Gnome, Halfling');

        $this->assertCount(3, $result);

        // All should be in same group (OR logic)
        $this->assertEquals(1, $result[0]['group_id']);
        $this->assertEquals(1, $result[1]['group_id']);
        $this->assertEquals(1, $result[2]['group_id']);

        // All should be races
        foreach ($result as $prereq) {
            $this->assertEquals(Race::class, $prereq['prerequisite_type']);
            $this->assertNotNull($prereq['prerequisite_id']);
        }
    }

    #[Test]
    public function it_parses_proficiency_prerequisite()
    {
        // "Proficiency with medium armor"
        $result = $this->parser->parsePrerequisites('Proficiency with medium armor');

        $this->assertCount(1, $result);
        $this->assertEquals(ProficiencyType::class, $result[0]['prerequisite_type']);
        $this->assertNotNull($result[0]['prerequisite_id']); // Should find Medium Armor proficiency
        $this->assertEquals(1, $result[0]['group_id']);
    }

    #[Test]
    public function it_parses_heavy_armor_proficiency_prerequisite()
    {
        // "Proficiency with heavy armor"
        $result = $this->parser->parsePrerequisites('Proficiency with heavy armor');

        $this->assertCount(1, $result);
        $this->assertEquals(ProficiencyType::class, $result[0]['prerequisite_type']);

        $profType = ProficiencyType::where('name', 'LIKE', '%Heavy%Armor%')->first();
        $this->assertNotNull($profType);
        $this->assertEquals($profType->id, $result[0]['prerequisite_id']);
    }

    #[Test]
    public function it_parses_light_armor_proficiency_prerequisite()
    {
        // "Proficiency with light armor"
        $result = $this->parser->parsePrerequisites('Proficiency with light armor');

        $this->assertCount(1, $result);
        $this->assertEquals(ProficiencyType::class, $result[0]['prerequisite_type']);
        $this->assertNotNull($result[0]['prerequisite_id']);
    }

    #[Test]
    public function it_parses_spellcasting_feature_as_freeform()
    {
        // "The ability to cast at least one spell" (no entity exists for this yet)
        $result = $this->parser->parsePrerequisites('The ability to cast at least one spell');

        $this->assertCount(1, $result);
        $this->assertNull($result[0]['prerequisite_type']);
        $this->assertNull($result[0]['prerequisite_id']);
        $this->assertEquals('The ability to cast at least one spell', $result[0]['description']);
        $this->assertEquals(1, $result[0]['group_id']);
    }

    #[Test]
    public function it_parses_pact_magic_feature_as_freeform()
    {
        // "Spellcasting or Pact Magic feature"
        $result = $this->parser->parsePrerequisites('Spellcasting or Pact Magic feature');

        $this->assertCount(1, $result);
        $this->assertNull($result[0]['prerequisite_type']);
        $this->assertNull($result[0]['prerequisite_id']);
        $this->assertEquals('Spellcasting or Pact Magic feature', $result[0]['description']);
    }

    #[Test]
    public function it_parses_complex_and_or_prerequisite()
    {
        // "Dwarf, Gnome, Halfling, Small Race, Proficiency in Acrobatics"
        // This should be: (Dwarf OR Gnome OR Halfling) AND (Proficiency in Acrobatics)
        // Note: "Small Race" might not exist as entity, treat as free-form or skip

        Race::factory()->create(['name' => 'Dwarf', 'slug' => 'dwarf']);
        Race::factory()->create(['name' => 'Gnome', 'slug' => 'gnome']);
        Race::factory()->create(['name' => 'Halfling', 'slug' => 'halfling']);

        $result = $this->parser->parsePrerequisites('Dwarf, Gnome, Halfling, Small Race, Proficiency in Acrobatics');

        // Should have at least 4 prerequisites:
        // - 3 races (Dwarf, Gnome, Halfling) in group 1
        // - 1 proficiency (Acrobatics - may be free-form if not in proficiency_types) in group 2
        // - "Small Race" in group 1 as free-form

        $this->assertGreaterThanOrEqual(4, count($result));

        // Check that races are in group 1
        $racePrereqs = array_filter($result, fn ($p) => $p['prerequisite_type'] === Race::class);
        $this->assertCount(3, $racePrereqs);
        foreach ($racePrereqs as $prereq) {
            $this->assertEquals(1, $prereq['group_id']);
        }

        // Check that there's a proficiency/skill in group 2 (AND logic with races)
        // Should be a Skill (Acrobatics), not ProficiencyType
        $group2Prereqs = array_filter($result, fn ($p) => $p['group_id'] === 2);
        $this->assertCount(1, $group2Prereqs);

        $skillPrereq = reset($group2Prereqs);
        // Should be Skill model (Acrobatics is a skill)
        $this->assertEquals(Skill::class, $skillPrereq['prerequisite_type']);

        $acrobatics = Skill::where('name', 'Acrobatics')->first();
        $this->assertNotNull($acrobatics);
        $this->assertEquals($acrobatics->id, $skillPrereq['prerequisite_id']);
    }

    #[Test]
    public function it_handles_empty_prerequisite()
    {
        $result = $this->parser->parsePrerequisites('');

        $this->assertEmpty($result);
    }

    #[Test]
    public function it_handles_null_prerequisite()
    {
        $result = $this->parser->parsePrerequisites(null);

        $this->assertEmpty($result);
    }

    #[Test]
    public function it_parses_charisma_prerequisite()
    {
        // "Charisma 13 or higher"
        $result = $this->parser->parsePrerequisites('Charisma 13 or higher');

        $this->assertCount(1, $result);

        $abilityScore = AbilityScore::where('code', 'CHA')->first();
        $this->assertNotNull($abilityScore);

        $this->assertEquals(AbilityScore::class, $result[0]['prerequisite_type']);
        $this->assertEquals($abilityScore->id, $result[0]['prerequisite_id']);
        $this->assertEquals(13, $result[0]['minimum_value']);
    }

    #[Test]
    public function it_parses_dragonborn_prerequisite()
    {
        // "Dragonborn"
        Race::factory()->create(['name' => 'Dragonborn', 'slug' => 'dragonborn']);

        $result = $this->parser->parsePrerequisites('Dragonborn');

        $this->assertCount(1, $result);
        $this->assertEquals(Race::class, $result[0]['prerequisite_type']);

        $race = Race::where('name', 'Dragonborn')->first();
        $this->assertEquals($race->id, $result[0]['prerequisite_id']);
    }

    #[Test]
    public function it_parses_races_with_skill_suffix()
    {
        // "Dwarf, Gnome, Halfling, Small Race, Proficiency in the Acrobatics skill"
        // This is the real-world Squat Nimbleness case
        Race::factory()->create(['name' => 'Dwarf', 'slug' => 'dwarf']);
        Race::factory()->create(['name' => 'Gnome', 'slug' => 'gnome']);
        Race::factory()->create(['name' => 'Halfling', 'slug' => 'halfling']);

        $result = $this->parser->parsePrerequisites('Dwarf, Gnome, Halfling, Small Race, Proficiency in the Acrobatics skill');

        // Should have at least 5 prerequisites:
        // - 3 races (Dwarf, Gnome, Halfling) in group 1
        // - 1 free-form "Small Race" in group 1
        // - 1 skill (Acrobatics) in group 2
        $this->assertGreaterThanOrEqual(5, count($result));

        // Check that races are in group 1
        $racePrereqs = array_filter($result, fn ($p) => $p['prerequisite_type'] === Race::class);
        $this->assertCount(3, $racePrereqs);
        foreach ($racePrereqs as $prereq) {
            $this->assertEquals(1, $prereq['group_id']);
        }

        // Check that there's a skill in group 2 (AND logic with races)
        $group2Prereqs = array_filter($result, fn ($p) => $p['group_id'] === 2);
        $this->assertCount(1, $group2Prereqs);

        $skillPrereq = reset($group2Prereqs);
        // Should be Skill model (Acrobatics is a skill)
        $this->assertEquals(Skill::class, $skillPrereq['prerequisite_type']);

        $acrobatics = Skill::where('name', 'Acrobatics')->first();
        $this->assertNotNull($acrobatics);
        $this->assertEquals($acrobatics->id, $skillPrereq['prerequisite_id']);
    }
}
