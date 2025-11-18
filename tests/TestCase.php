<?php

namespace Tests;

use App\Models\AbilityScore;
use App\Models\DamageType;
use App\Models\Size;
use App\Models\Skill;
use App\Models\Source;
use App\Models\SpellSchool;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Helper methods for commonly used lookup data
     */
    protected function getSize(string $code): Size
    {
        return Size::where('code', $code)->firstOrFail();
    }

    protected function getAbilityScore(string $code): AbilityScore
    {
        return AbilityScore::where('code', $code)->firstOrFail();
    }

    protected function getSkill(string $name): Skill
    {
        return Skill::where('name', $name)->firstOrFail();
    }

    protected function getSpellSchool(string $code): SpellSchool
    {
        return SpellSchool::where('code', $code)->firstOrFail();
    }

    protected function getDamageType(string $name): DamageType
    {
        return DamageType::where('name', $name)->firstOrFail();
    }

    protected function getSource(string $code): Source
    {
        return Source::where('code', $code)->firstOrFail();
    }
}
