<?php

/**
 * Entity Cache Performance Benchmark
 *
 * Run this script via tinker to measure cache performance:
 * docker compose exec php php artisan tinker
 * require 'tests/Benchmarks/EntityCacheBenchmark.php';
 */

use App\Services\Cache\EntityCacheService;
use App\Models\{Spell, Item, Monster, CharacterClass, Race, Background, Feat};
use Illuminate\Support\Facades\Cache;

function benchmarkEntity(string $entityType, string $modelClass, int $sampleId = 1): array
{
    $cache = app(EntityCacheService::class);
    $methodName = 'get' . ucfirst($entityType);

    // Clear cache for clean test
    Cache::flush();

    // Benchmark: Cold cache (database query)
    $coldTimes = [];
    for ($i = 0; $i < 5; $i++) {
        Cache::forget("entity:{$entityType}:{$sampleId}");
        $start = microtime(true);
        $result = $cache->$methodName($sampleId);
        $coldTimes[] = (microtime(true) - $start) * 1000;
    }
    $avgCold = round(array_sum($coldTimes) / count($coldTimes), 2);

    // Benchmark: Warm cache (Redis)
    $warmTimes = [];
    for ($i = 0; $i < 10; $i++) {
        $start = microtime(true);
        $result = $cache->$methodName($sampleId);
        $warmTimes[] = (microtime(true) - $start) * 1000;
    }
    $avgWarm = round(array_sum($warmTimes) / count($warmTimes), 2);

    // Calculate improvement
    $improvement = round((1 - $avgWarm / $avgCold) * 100, 1);
    $speedIncrease = round($avgCold / $avgWarm, 1);

    return [
        'entity_type' => ucfirst($entityType),
        'cold_cache_ms' => $avgCold,
        'warm_cache_ms' => $avgWarm,
        'improvement_pct' => $improvement,
        'speed_increase' => "{$speedIncrease}x",
        'sample_id' => $sampleId,
    ];
}

echo "\n=== Entity Cache Performance Benchmark ===\n\n";
echo "Testing cache performance for all 7 entity types...\n";
echo "Each test runs 5 cold cache iterations and 10 warm cache iterations.\n\n";

$results = [];

// Benchmark each entity type
$results[] = benchmarkEntity('spell', Spell::class, 1);
$results[] = benchmarkEntity('item', Item::class, 1);
$results[] = benchmarkEntity('monster', Monster::class, 1);
$results[] = benchmarkEntity('class', CharacterClass::class, 1);
$results[] = benchmarkEntity('race', Race::class, 1);
$results[] = benchmarkEntity('background', Background::class, 1);
$results[] = benchmarkEntity('feat', Feat::class, 1);

// Display results table
printf("%-15s | %-12s | %-14s | %-12s | %-14s\n",
    'Entity Type', 'Cold (DB)', 'Warm (Cache)', 'Improvement', 'Speed Increase');
echo str_repeat('-', 80) . "\n";

foreach ($results as $result) {
    printf("%-15s | %9.2f ms | %11.2f ms | %10.1f%% | %14s\n",
        $result['entity_type'],
        $result['cold_cache_ms'],
        $result['warm_cache_ms'],
        $result['improvement_pct'],
        $result['speed_increase']
    );
}

// Calculate overall stats
$avgCold = round(array_sum(array_column($results, 'cold_cache_ms')) / count($results), 2);
$avgWarm = round(array_sum(array_column($results, 'warm_cache_ms')) / count($results), 2);
$avgImprovement = round(array_sum(array_column($results, 'improvement_pct')) / count($results), 1);

echo str_repeat('-', 80) . "\n";
printf("%-15s | %9.2f ms | %11.2f ms | %10.1f%% | %14s\n",
    'AVERAGE',
    $avgCold,
    $avgWarm,
    $avgImprovement,
    round($avgCold / $avgWarm, 1) . 'x'
);

echo "\n=== Summary ===\n";
echo "Average improvement: {$avgImprovement}%\n";
echo "Average cold cache: {$avgCold}ms\n";
echo "Average warm cache: {$avgWarm}ms\n";
echo "Database load reduction: " . round($avgImprovement, 0) . "%\n";

echo "\nBenchmark complete! 🚀\n\n";

return $results;
