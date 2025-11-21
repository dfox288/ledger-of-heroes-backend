<?php

namespace Tests\Unit\Services\Importers\Concerns;

use App\Models\DamageType;
use App\Models\ItemType;
use App\Models\Source;
use App\Services\Importers\Concerns\CachesLookupTables;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

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

    #[\PHPUnit\Framework\Attributes\Test]
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

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_caches_lookup_by_code()
    {
        // First call - hits database
        $result1 = $this->importer->testCachedFind(Source::class, 'code', 'PHB');

        // Second call - hits cache
        $result2 = $this->importer->testCachedFind(Source::class, 'code', 'PHB');

        $this->assertSame($result1, $result2);
        $this->assertEquals('PHB', $result1->code);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_id_only_when_requested()
    {
        $itemType = ItemType::where('code', 'W')->first();

        $result = $this->importer->testCachedFindId(ItemType::class, 'code', 'W');

        $this->assertIsInt($result);
        $this->assertEquals($itemType->id, $result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_null_for_missing_records_when_using_first()
    {
        $result = $this->importer->testCachedFind(Source::class, 'code', 'NONEXISTENT', useFail: false);

        $this->assertNull($result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_throws_exception_for_missing_records_when_using_first_or_fail()
    {
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        $this->importer->testCachedFind(Source::class, 'code', 'NONEXISTENT', useFail: true);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_caches_null_results()
    {
        // First call - hits database
        $result1 = $this->importer->testCachedFind(Source::class, 'code', 'NONEXISTENT', useFail: false);

        // Second call - should return cached null
        $result2 = $this->importer->testCachedFind(Source::class, 'code', 'NONEXISTENT', useFail: false);

        $this->assertNull($result1);
        $this->assertNull($result2);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_multiple_model_types()
    {
        $source = $this->importer->testCachedFind(Source::class, 'code', 'PHB');
        $itemType = $this->importer->testCachedFind(ItemType::class, 'code', 'W');
        $damageType = $this->importer->testCachedFind(DamageType::class, 'code', 'S');

        $this->assertInstanceOf(Source::class, $source);
        $this->assertInstanceOf(ItemType::class, $itemType);
        $this->assertInstanceOf(DamageType::class, $damageType);
    }

    #[\PHPUnit\Framework\Attributes\Test]
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

    #[\PHPUnit\Framework\Attributes\Test]
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
