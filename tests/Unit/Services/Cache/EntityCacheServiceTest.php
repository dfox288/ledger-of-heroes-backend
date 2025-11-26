<?php

namespace Tests\Unit\Services\Cache;

use App\Models\Item;
use App\Models\Monster;
use App\Models\Spell;
use App\Services\Cache\EntityCacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('unit-db')]
class EntityCacheServiceTest extends TestCase
{
    use RefreshDatabase;

    private EntityCacheService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new EntityCacheService;
        Cache::flush();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function cache_miss_loads_spell_from_database(): void
    {
        $spell = Spell::factory()->create(['name' => 'Fireball']);

        // Clear cache to ensure miss
        Cache::flush();

        // First call should hit database
        $result = $this->service->getSpell($spell->id);

        $this->assertNotNull($result);
        $this->assertEquals('Fireball', $result->name);
        $this->assertEquals($spell->id, $result->id);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function cache_hit_returns_cached_spell(): void
    {
        $spell = Spell::factory()->create(['name' => 'Magic Missile']);

        // First call loads from DB and caches
        $first = $this->service->getSpell($spell->id);

        // Second call should return cached version
        $second = $this->service->getSpell($spell->id);

        $this->assertEquals($first->id, $second->id);
        $this->assertEquals($first->name, $second->name);

        // Verify cache key exists
        $cacheKey = "entity:spell:{$spell->id}";
        $this->assertTrue(Cache::has($cacheKey));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function get_spell_by_slug_resolves_to_id(): void
    {
        $spell = Spell::factory()->create([
            'name' => 'Fireball',
            'slug' => 'fireball',
        ]);

        // Call with slug should resolve to numeric ID
        $result = $this->service->getSpell('fireball');

        $this->assertNotNull($result);
        $this->assertEquals($spell->id, $result->id);
        $this->assertEquals('Fireball', $result->name);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function cache_key_format_is_consistent(): void
    {
        $spell = Spell::factory()->create(['name' => 'Shield']);

        $this->service->getSpell($spell->id);

        // Expected format: entity:{type}:{id}
        $expectedKey = "entity:spell:{$spell->id}";
        $this->assertTrue(Cache::has($expectedKey));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function invalidate_spell_clears_cache(): void
    {
        $spell = Spell::factory()->create(['name' => 'Cure Wounds']);

        // Cache the spell
        $this->service->getSpell($spell->id);

        $cacheKey = "entity:spell:{$spell->id}";
        $this->assertTrue(Cache::has($cacheKey));

        // Invalidate specific spell
        $this->service->invalidateSpell($spell->id);

        // Cache should be cleared
        $this->assertFalse(Cache::has($cacheKey));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function invalidate_all_clears_entity_type_cache(): void
    {
        $spell1 = Spell::factory()->create(['name' => 'Fireball']);
        $spell2 = Spell::factory()->create(['name' => 'Lightning Bolt']);
        $item = Item::factory()->create(['name' => 'Sword']);

        // Cache multiple entities
        $this->service->getSpell($spell1->id);
        $this->service->getSpell($spell2->id);
        $this->service->getItem($item->id);

        // Invalidate all spells
        $this->service->invalidateAll('spell');

        // Spell caches should be cleared
        $this->assertFalse(Cache::has("entity:spell:{$spell1->id}"));
        $this->assertFalse(Cache::has("entity:spell:{$spell2->id}"));

        // Item cache should still exist
        $this->assertTrue(Cache::has("entity:item:{$item->id}"));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function clear_all_clears_all_entity_caches(): void
    {
        $spell = Spell::factory()->create(['name' => 'Fireball']);
        $item = Item::factory()->create(['name' => 'Sword']);

        // Cache both
        $this->service->getSpell($spell->id);
        $this->service->getItem($item->id);

        // Clear all caches
        $this->service->clearAll();

        // All entity caches should be cleared
        $this->assertFalse(Cache::has("entity:spell:{$spell->id}"));
        $this->assertFalse(Cache::has("entity:item:{$item->id}"));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function cache_miss_loads_item_from_database(): void
    {
        $item = Item::factory()->create(['name' => 'Longsword']);

        Cache::flush();

        // First call should hit database
        $result = $this->service->getItem($item->id);

        $this->assertNotNull($result);
        $this->assertEquals('Longsword', $result->name);
        $this->assertEquals($item->id, $result->id);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function get_monster_with_relationships_eager_loaded(): void
    {
        $monster = Monster::factory()->create(['name' => 'Dragon']);

        // Get monster (should eager-load relationships)
        $result = $this->service->getMonster($monster->id);

        $this->assertNotNull($result);
        $this->assertEquals('Dragon', $result->name);

        // Verify relationships are loaded (no additional queries)
        $this->assertTrue($result->relationLoaded('size'));
        $this->assertTrue($result->relationLoaded('sources'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function ttl_is_fifteen_minutes(): void
    {
        // This test verifies the TTL constant
        $reflection = new \ReflectionClass(EntityCacheService::class);
        $constants = $reflection->getConstants();

        $this->assertArrayHasKey('TTL', $constants);
        $this->assertEquals(900, $constants['TTL'], 'TTL should be 900 seconds (15 minutes)');
    }
}
