<?php

namespace Tests\Unit\Services\Importers\Concerns;

use App\Exceptions\Lookup\EntityNotFoundException;
use App\Models\DamageType;
use App\Models\ItemType;
use App\Models\Source;
use App\Services\Importers\Concerns\CachesLookupTables;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('unit-db')]
class CachesLookupTablesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Indicates whether the default seeder should run before each test.
     *
     * @var bool
     */
    protected $seed = true;

    private TestImporterWithCache $importer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importer = new TestImporterWithCache;
    }

    #[Test]
    public function it_caches_lookup_by_id()
    {
        $source = Source::where('code', 'PHB')->first();

        // First call - hits database
        $result1 = $this->importer->testCachedFind(Source::class, 'id', $source->id);

        // Second call - hits cache
        $result2 = $this->importer->testCachedFind(Source::class, 'id', $source->id);

        $this->assertSame($result1, $result2);
        $this->assertEquals($source->id, $result1->id);
    }

    #[Test]
    public function it_caches_lookup_by_code()
    {
        // First call - hits database
        $result1 = $this->importer->testCachedFind(Source::class, 'code', 'PHB');

        // Second call - hits cache
        $result2 = $this->importer->testCachedFind(Source::class, 'code', 'PHB');

        $this->assertSame($result1, $result2);
        $this->assertEquals('PHB', $result1->code);
    }

    #[Test]
    public function it_returns_id_only_when_requested()
    {
        $itemType = ItemType::where('code', 'W')->first();

        $result = $this->importer->testCachedFindId(ItemType::class, 'code', 'W');

        $this->assertIsInt($result);
        $this->assertEquals($itemType->id, $result);
    }

    #[Test]
    public function it_returns_null_for_missing_records_when_using_first()
    {
        $result = $this->importer->testCachedFind(Source::class, 'code', 'NONEXISTENT', useFail: false);

        $this->assertNull($result);
    }

    #[Test]
    public function it_throws_exception_for_missing_records_when_using_first_or_fail()
    {
        // Now throws EntityNotFoundException instead of ModelNotFoundException
        $this->expectException(EntityNotFoundException::class);

        $this->importer->testCachedFind(Source::class, 'code', 'NONEXISTENT', useFail: true);
    }

    #[Test]
    public function it_throws_entity_not_found_exception_with_context()
    {
        $this->expectException(EntityNotFoundException::class);
        $this->expectExceptionMessage('Source not found');
        $this->expectExceptionCode(404);

        $this->importer->testCachedFind(Source::class, 'code', 'NONEXISTENT', useFail: true);
    }

    #[Test]
    public function it_caches_null_results()
    {
        // First call - hits database
        $result1 = $this->importer->testCachedFind(Source::class, 'code', 'NONEXISTENT', useFail: false);

        // Second call - should return cached null
        $result2 = $this->importer->testCachedFind(Source::class, 'code', 'NONEXISTENT', useFail: false);

        $this->assertNull($result1);
        $this->assertNull($result2);
    }

    #[Test]
    public function it_handles_multiple_model_types()
    {
        $source = $this->importer->testCachedFind(Source::class, 'code', 'PHB');
        $itemType = $this->importer->testCachedFind(ItemType::class, 'code', 'W');
        $damageType = $this->importer->testCachedFind(DamageType::class, 'code', 'S');

        $this->assertInstanceOf(Source::class, $source);
        $this->assertInstanceOf(ItemType::class, $itemType);
        $this->assertInstanceOf(DamageType::class, $damageType);
    }

    #[Test]
    public function it_normalizes_cache_keys_by_uppercasing_value()
    {
        // Test with lowercase
        $result1 = $this->importer->testCachedFind(DamageType::class, 'code', 's');

        // Test with uppercase
        $result2 = $this->importer->testCachedFind(DamageType::class, 'code', 'S');

        // Test with mixed case (doesn't apply to single char, but tests the concept)
        $result3 = $this->importer->testCachedFind(Source::class, 'code', 'phb');
        $result4 = $this->importer->testCachedFind(Source::class, 'code', 'PHB');

        $this->assertSame($result1, $result2);
        $this->assertSame($result3, $result4);
    }

    #[Test]
    public function it_returns_nullable_id_for_missing_records()
    {
        $result = $this->importer->testCachedFindId(Source::class, 'code', 'NONEXISTENT', useFail: false);

        $this->assertNull($result);
    }
}

/**
 * Test stub class that uses the CachesLookupTables trait
 */
class TestImporterWithCache
{
    use CachesLookupTables;

    public function testCachedFind(string $model, string $column, mixed $value, bool $useFail = true): mixed
    {
        return $this->cachedFind($model, $column, $value, $useFail);
    }

    public function testCachedFindId(string $model, string $column, mixed $value, bool $useFail = true): ?int
    {
        return $this->cachedFindId($model, $column, $value, $useFail);
    }
}
