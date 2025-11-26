<?php

namespace Tests\Support;

use PHPUnit\Runner\Extension\Extension;
use PHPUnit\Runner\Extension\Facade;
use PHPUnit\Runner\Extension\ParameterCollection;
use PHPUnit\TextUI\Configuration\Configuration;

/**
 * PHPUnit extension that ensures test database is populated before search tests.
 *
 * This extension runs `import:all --env=testing` once before any search tests run,
 * dramatically speeding up test execution by avoiding per-test imports.
 *
 * Usage: Add to phpunit.xml:
 *   <extensions>
 *       <bootstrap class="Tests\Support\SearchTestExtension"/>
 *   </extensions>
 */
final class SearchTestExtension implements Extension
{
    private static bool $imported = false;

    public function bootstrap(Configuration $configuration, Facade $facade, ParameterCollection $parameters): void
    {
        $facade->registerSubscriber(new SearchTestSubscriber);
    }
}
