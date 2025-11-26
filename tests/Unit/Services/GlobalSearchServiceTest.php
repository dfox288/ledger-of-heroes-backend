<?php

namespace Tests\Unit\Services;

use App\Services\Search\GlobalSearchService;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('unit-pure')]

class GlobalSearchServiceTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function it_searches_across_multiple_models(): void
    {
        $service = new GlobalSearchService;
        $results = $service->search('fireball', types: ['spell', 'item']);

        $this->assertIsArray($results);
        $this->assertArrayHasKey('spells', $results);
        $this->assertArrayHasKey('items', $results);
        $this->assertCount(2, $results);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_limits_results_per_type(): void
    {
        $service = new GlobalSearchService;
        $results = $service->search('fire', limit: 5);

        foreach ($results as $type => $items) {
            $this->assertLessThanOrEqual(5, $items->count());
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_searches_all_types_by_default(): void
    {
        $service = new GlobalSearchService;
        $results = $service->search('dragon');

        $this->assertArrayHasKey('spells', $results);
        $this->assertArrayHasKey('items', $results);
        $this->assertArrayHasKey('races', $results);
        $this->assertArrayHasKey('classes', $results);
        $this->assertArrayHasKey('backgrounds', $results);
        $this->assertArrayHasKey('feats', $results);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_available_types(): void
    {
        $service = new GlobalSearchService;
        $types = $service->getAvailableTypes();

        $this->assertIsArray($types);
        $this->assertContains('spell', $types);
        $this->assertContains('item', $types);
        $this->assertContains('race', $types);
        $this->assertContains('class', $types);
        $this->assertContains('background', $types);
        $this->assertContains('feat', $types);
    }
}
