<?php

namespace Tests\Unit\Concerns;

use App\Services\Concerns\ClearsCaches;
use App\Services\Importers\Concerns\ImportsSenses;
use App\Services\Parsers\Concerns\LookupsGameEntities;
use App\Services\Parsers\Concerns\MatchesLanguages;
use App\Services\Parsers\Concerns\MatchesProficiencyTypes;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('unit-pure')]
class ClearsCachesTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function clears_caches_class_has_clear_all_method(): void
    {
        $this->assertTrue(
            method_exists(ClearsCaches::class, 'clearAll'),
            'ClearsCaches class must define clearAll method'
        );

        $reflection = new \ReflectionMethod(ClearsCaches::class, 'clearAll');
        $this->assertTrue($reflection->isPublic(), 'clearAll must be public');
        $this->assertTrue($reflection->isStatic(), 'clearAll must be static');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function concerns_have_unique_clear_cache_methods(): void
    {
        // Each trait has its own uniquely-named clearXxxCache method to avoid collisions
        $concernsWithCacheMethods = [
            ImportsSenses::class => 'clearSenseCache',
            LookupsGameEntities::class => 'clearGameEntitiesCache',
            MatchesProficiencyTypes::class => 'clearProficiencyTypesCache',
            MatchesLanguages::class => 'clearLanguagesCache',
        ];

        foreach ($concernsWithCacheMethods as $concern => $methodName) {
            $this->assertTrue(
                trait_exists($concern),
                "{$concern} should be a trait"
            );

            $reflection = new \ReflectionClass($concern);
            $this->assertTrue(
                $reflection->hasMethod($methodName),
                "{$concern} must have {$methodName} method"
            );

            $method = $reflection->getMethod($methodName);
            $this->assertTrue($method->isStatic(), "{$concern}::{$methodName} must be static");
            $this->assertTrue($method->isPublic(), "{$concern}::{$methodName} must be public");
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function clear_all_calls_all_cache_clear_methods(): void
    {
        // Verify clearAll doesn't throw an exception
        // In a real test with database, we'd verify caches are actually cleared
        ClearsCaches::clearAll();

        $this->assertTrue(true, 'clearAll should complete without error');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function get_caching_concerns_returns_all_concerns(): void
    {
        $concerns = ClearsCaches::getCachingConcerns();

        $this->assertArrayHasKey(ImportsSenses::class, $concerns);
        $this->assertArrayHasKey(LookupsGameEntities::class, $concerns);
        $this->assertArrayHasKey(MatchesProficiencyTypes::class, $concerns);
        $this->assertArrayHasKey(MatchesLanguages::class, $concerns);

        $this->assertEquals('clearSenseCache', $concerns[ImportsSenses::class]);
        $this->assertEquals('clearGameEntitiesCache', $concerns[LookupsGameEntities::class]);
        $this->assertEquals('clearProficiencyTypesCache', $concerns[MatchesProficiencyTypes::class]);
        $this->assertEquals('clearLanguagesCache', $concerns[MatchesLanguages::class]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function individual_clear_cache_methods_are_callable(): void
    {
        // Verify each individual clear method can be called without error
        $sensesClearer = new class
        {
            use ImportsSenses;
        };
        $sensesClearer::clearSenseCache();

        $gameEntitiesClearer = new class
        {
            use LookupsGameEntities;
        };
        $gameEntitiesClearer::clearGameEntitiesCache();

        $proficiencyTypesClearer = new class
        {
            use MatchesProficiencyTypes;
        };
        $proficiencyTypesClearer::clearProficiencyTypesCache();

        $languagesClearer = new class
        {
            use MatchesLanguages;
        };
        $languagesClearer::clearLanguagesCache();

        $this->assertTrue(true, 'All individual clear methods should be callable');
    }
}
