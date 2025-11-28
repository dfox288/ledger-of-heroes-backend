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
     * The seeder class to use for test database setup.
     *
     * Default: LookupSeeder - seeds only lookup tables (ability scores, spell schools, etc.)
     * This allows tests to create their own entity data via factories without conflicts.
     *
     * For tests that need full fixture data (search tests), override with:
     *   protected $seeder = \Database\Seeders\TestDatabaseSeeder::class;
     *
     * @var string
     */
    protected $seeder = \Database\Seeders\LookupSeeder::class;

    /**
     * Store original error/exception handlers to restore after test.
     *
     * PHPUnit 11 tracks changes to global handlers and marks tests as risky if
     * they're not restored. Guzzle (used by Meilisearch) temporarily sets handlers
     * during HTTP requests, which triggers this warning. We save the handlers at
     * test start and restore them in tearDown to prevent risky test warnings.
     */
    private mixed $savedErrorHandler = null;

    private mixed $savedExceptionHandlerForRestore = null;

    protected function setUp(): void
    {
        // Capture current handlers before test runs
        $this->savedErrorHandler = set_error_handler(fn () => false);
        restore_error_handler();

        $this->savedExceptionHandlerForRestore = set_exception_handler(fn () => null);
        restore_exception_handler();

        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // Restore handlers to state captured in setUp
        // This prevents PHPUnit 11 risky test warnings from Guzzle/Meilisearch
        //
        // PHPUnit 11 tracks error/exception handler changes and marks tests "risky"
        // if they're not restored. The issue: Guzzle (used by Meilisearch) calls
        // restore_error_handler() during HTTP requests, which can pop PHPUnit's
        // handler off the stack. PHPUnit then complains about missing handlers.
        //
        // Solution: If there's no error handler after the test (because Guzzle
        // popped it), reinstall the original one we captured in setUp.

        // Check current error handler state
        $currentError = set_error_handler(fn () => false);
        restore_error_handler();

        // If handler was popped (now null) but we had one before, reinstall it
        if ($currentError === null && $this->savedErrorHandler !== null) {
            set_error_handler($this->savedErrorHandler);
        }

        // Check current exception handler state
        $currentException = set_exception_handler(fn () => null);
        restore_exception_handler();

        // If handler was popped (now null) but we had one before, reinstall it
        if ($currentException === null && $this->savedExceptionHandlerForRestore !== null) {
            set_exception_handler($this->savedExceptionHandlerForRestore);
        }
    }

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
        return Source::where('code', $code)->first()
            ?? Source::factory()->create(['code' => $code, 'name' => "Test Source {$code}"]);
    }
}
