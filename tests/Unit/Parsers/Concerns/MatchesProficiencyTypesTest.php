<?php

namespace Tests\Unit\Parsers\Concerns;

use App\Services\Parsers\Concerns\MatchesProficiencyTypes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('unit-db')]
class MatchesProficiencyTypesTest extends TestCase
{
    use MatchesProficiencyTypes, RefreshDatabase;

    #[Test]
    public function it_infers_armor_type_from_name()
    {
        $this->assertEquals('armor', $this->inferProficiencyTypeFromName('Light Armor'));
        $this->assertEquals('armor', $this->inferProficiencyTypeFromName('medium armor'));
        $this->assertEquals('armor', $this->inferProficiencyTypeFromName('Shields'));
    }

    #[Test]
    public function it_infers_weapon_type_from_name()
    {
        $this->assertEquals('weapon', $this->inferProficiencyTypeFromName('Longsword'));
        $this->assertEquals('weapon', $this->inferProficiencyTypeFromName('Simple Weapons'));
        $this->assertEquals('weapon', $this->inferProficiencyTypeFromName('martial weapon'));
    }

    #[Test]
    public function it_infers_tool_type_from_name()
    {
        $this->assertEquals('tool', $this->inferProficiencyTypeFromName("Smith's Tools"));
        $this->assertEquals('tool', $this->inferProficiencyTypeFromName('Thieves Kit'));
        $this->assertEquals('tool', $this->inferProficiencyTypeFromName('Gaming Set'));
        $this->assertEquals('tool', $this->inferProficiencyTypeFromName('Musical Instrument'));
    }

    #[Test]
    public function it_defaults_to_skill_for_unknown_types()
    {
        $this->assertEquals('skill', $this->inferProficiencyTypeFromName('Acrobatics'));
        $this->assertEquals('skill', $this->inferProficiencyTypeFromName('Unknown'));
    }

    #[Test]
    public function it_returns_null_for_choice_based_tool_proficiencies()
    {
        // This is the Monk's tool proficiency - should NOT match "Net" weapon
        // because "any one" contains "net" as a substring
        $monkToolProf = "Any one type of Artisan's Tools or any one Musical Instrument of your choice";
        $this->assertNull($this->matchProficiencyType($monkToolProf));

        // Other choice-based proficiencies should also return null
        $this->assertNull($this->matchProficiencyType('One type of gaming set'));
        $this->assertNull($this->matchProficiencyType('Choose one from the following'));
        $this->assertNull($this->matchProficiencyType('Any musical instrument'));
    }

    #[Test]
    public function it_still_matches_specific_tool_proficiencies()
    {
        // Specific tools should still match
        $smithsTools = $this->matchProficiencyType("Smith's Tools");
        $this->assertNotNull($smithsTools);
        $this->assertEquals('tool', $smithsTools->category);

        $thievesTools = $this->matchProficiencyType("Thieves' Tools");
        $this->assertNotNull($thievesTools);
        $this->assertEquals('tool', $thievesTools->category);
    }
}
