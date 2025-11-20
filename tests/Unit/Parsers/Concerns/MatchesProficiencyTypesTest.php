<?php

namespace Tests\Unit\Parsers\Concerns;

use App\Services\Parsers\Concerns\MatchesProficiencyTypes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

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
}
