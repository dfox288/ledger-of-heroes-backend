<?php

namespace Tests\Support;

use PHPUnit\Event\Test\PreparationStarted;
use PHPUnit\Event\Test\PreparationStartedSubscriber;

/**
 * Subscriber that imports test data before search tests run.
 *
 * This subscriber checks if the test database has data before running search tests.
 * If no data exists, it runs import:all to populate it. This happens once per test run.
 */
final class SearchTestSubscriber implements PreparationStartedSubscriber
{
    private static bool $checked = false;

    public function notify(PreparationStarted $event): void
    {
        // Only check once per test run
        if (self::$checked) {
            return;
        }

        // Check if this is a search test (by group attribute)
        $test = $event->test();
        $className = $test->className();

        // Use reflection to check for search group attributes
        if (! class_exists($className)) {
            return;
        }

        $reflection = new \ReflectionClass($className);
        $attributes = $reflection->getAttributes(\PHPUnit\Framework\Attributes\Group::class);

        $isSearchTest = false;
        foreach ($attributes as $attribute) {
            $group = $attribute->newInstance();
            if (in_array($group->name(), ['feature-search', 'search-isolated', 'search-imported'])) {
                $isSearchTest = true;
                break;
            }
        }

        if (! $isSearchTest) {
            return;
        }

        self::$checked = true;

        // Bootstrap Laravel to check database
        $app = require __DIR__.'/../../bootstrap/app.php';
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

        // Check if we have substantial data (not just seeders)
        $spellCount = \App\Models\Spell::count();

        if ($spellCount > 100) {
            echo "\n[SearchTestExtension] Test database has data ({$spellCount} spells), skipping import.\n";

            return;
        }

        echo "\n[SearchTestExtension] Importing test data for search tests...\n";
        echo "[SearchTestExtension] This may take 60-90 seconds on first run.\n";

        // Run import:all
        \Illuminate\Support\Facades\Artisan::call('import:all');

        echo "[SearchTestExtension] Import complete.\n\n";
    }
}
