<?php

namespace Tests\Unit\Services\Cache;

use App\Models\SpellSchool;
use App\Services\Cache\LookupCacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('unit-db')]
class LookupCacheServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Only seed if tables are empty to avoid duplicate key errors
        if (SpellSchool::count() === 0) {
            $this->seed();
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_caches_spell_schools_on_first_request(): void
    {
        Cache::flush();

        $service = new LookupCacheService;
        $schools = $service->getSpellSchools();

        $this->assertGreaterThan(0, $schools->count());
        $this->assertTrue(Cache::has('lookups:spell-schools:all'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_cached_spell_schools_on_subsequent_requests(): void
    {
        Cache::flush();
        $service = new LookupCacheService;

        // First request - cache miss
        DB::enableQueryLog();
        $service->getSpellSchools();
        $firstQueryCount = count(DB::getQueryLog());

        // Second request - cache hit (no queries)
        DB::flushQueryLog();
        $service->getSpellSchools();
        $secondQueryCount = count(DB::getQueryLog());

        $this->assertGreaterThan(0, $firstQueryCount);
        $this->assertEquals(0, $secondQueryCount); // No DB queries
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_caches_all_lookup_types(): void
    {
        Cache::flush();
        $service = new LookupCacheService;

        $service->getSpellSchools();
        $service->getDamageTypes();
        $service->getConditions();
        $service->getSizes();
        $service->getAbilityScores();
        $service->getLanguages();
        $service->getProficiencyTypes();

        $this->assertTrue(Cache::has('lookups:spell-schools:all'));
        $this->assertTrue(Cache::has('lookups:damage-types:all'));
        $this->assertTrue(Cache::has('lookups:conditions:all'));
        $this->assertTrue(Cache::has('lookups:sizes:all'));
        $this->assertTrue(Cache::has('lookups:ability-scores:all'));
        $this->assertTrue(Cache::has('lookups:languages:all'));
        $this->assertTrue(Cache::has('lookups:proficiency-types:all'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_uses_one_hour_ttl_for_lookups(): void
    {
        // Skip if not using Redis
        if (config('cache.default') !== 'redis') {
            $this->markTestSkipped('Redis cache required for TTL verification');
        }

        Cache::flush();
        $service = new LookupCacheService;

        $schools = $service->getSpellSchools();

        // Check TTL is approximately 1 hour (3600 seconds)
        $ttl = Cache::getRedis()->ttl('dnd_lookups:spell-schools:all');
        $this->assertGreaterThan(3500, $ttl); // Allow some variance
        $this->assertLessThan(3610, $ttl);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_clears_all_lookup_caches(): void
    {
        $service = new LookupCacheService;

        // Populate caches
        $service->getSpellSchools();
        $service->getDamageTypes();
        $service->getConditions();

        $this->assertTrue(Cache::has('lookups:spell-schools:all'));

        // Clear all
        $service->clearAll();

        $this->assertFalse(Cache::has('lookups:spell-schools:all'));
        $this->assertFalse(Cache::has('lookups:damage-types:all'));
        $this->assertFalse(Cache::has('lookups:conditions:all'));
    }
}
