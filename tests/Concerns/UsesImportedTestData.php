<?php

namespace Tests\Concerns;

use App\Models\Spell;

/**
 * Trait for tests that use pre-imported test data.
 *
 * This trait checks if the test database has been populated by import:all.
 * If data exists, it skips RefreshDatabase behavior to avoid destroying it.
 * If no data exists, it falls back to the test's normal setup.
 *
 * Benefits:
 * - Tests run ~10x faster when using pre-imported data
 * - First test run triggers import, subsequent runs reuse data
 * - Still works without pre-imported data (just slower)
 *
 * Usage:
 *   class MySearchTest extends TestCase
 *   {
 *       use UsesImportedTestData;  // Instead of RefreshDatabase
 *       use ClearsMeilisearchIndex;
 *       use WaitsForMeilisearch;
 *
 *       protected function setUp(): void
 *       {
 *           parent::setUp();
 *           $this->initializeTestData(); // Call this first
 *           // ... rest of setup
 *       }
 *   }
 */
trait UsesImportedTestData
{
    protected static bool $hasImportedData = false;

    protected static bool $checkedForData = false;

    /**
     * Initialize test data - checks for pre-imported data and handles accordingly.
     *
     * Call this at the start of setUp() before any other operations.
     */
    protected function initializeTestData(): void
    {
        // Only check once per test run
        if (! self::$checkedForData) {
            self::$hasImportedData = Spell::count() > 100; // Arbitrary threshold
            self::$checkedForData = true;
        }

        if (self::$hasImportedData) {
            // Data exists - don't reset the database, just configure indexes
            $this->artisan('search:configure-indexes');
        }
    }

    /**
     * Check if we're using pre-imported data.
     */
    protected function hasImportedData(): bool
    {
        return self::$hasImportedData;
    }

    /**
     * Get minimum expected record count for a model.
     *
     * Use this instead of assertGreaterThan(0, ...) to handle both
     * pre-imported data (many records) and factory data (few records).
     */
    protected function assertHasRecords(string $modelClass, string $message = ''): void
    {
        $count = $modelClass::count();
        $this->assertGreaterThan(0, $count, $message ?: "Expected {$modelClass} to have records");
    }
}
