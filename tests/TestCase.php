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
     * Indicates whether the default seeder should run before each test.
     *
     * @var bool
     */
    protected $seed = true;

    /**
     * Scout test isolation notes:
     *
     * Tests use SCOUT_PREFIX=test_ (configured in phpunit.xml) which creates separate indexes:
     * - Production: spells, items, races, etc.
     * - Test: test_spells, test_items, test_races, etc.
     *
     * This ensures test data never pollutes production indexes.
     * Test indexes are ephemeral and can be manually cleaned via:
     *   curl -X DELETE http://localhost:7700/indexes/test_spells
     *
     * We do NOT auto-flush test indexes in tearDown() because:
     * 1. removeAllFromSearch() may flush production indexes in some Scout versions
     * 2. Test index prefix isolation is sufficient for test isolation
     * 3. Test indexes use minimal space (<1MB typically)
     */

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
