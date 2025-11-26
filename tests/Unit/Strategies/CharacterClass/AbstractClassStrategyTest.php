<?php

namespace Tests\Unit\Strategies\CharacterClass;

use App\Services\Importers\Strategies\CharacterClass\AbstractClassStrategy;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('unit-db')]
class AbstractClassStrategyTest extends TestCase
{
    private ConcreteTestStrategy $strategy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->strategy = new ConcreteTestStrategy;
    }

    #[Test]
    public function it_tracks_warnings(): void
    {
        $this->assertEmpty($this->strategy->getWarnings());

        $this->strategy->addWarningPublic('Test warning');

        $this->assertEquals(['Test warning'], $this->strategy->getWarnings());
    }

    #[Test]
    public function it_tracks_metrics(): void
    {
        $this->assertEmpty($this->strategy->getMetrics());

        $this->strategy->incrementMetricPublic('test_count');
        $this->strategy->incrementMetricPublic('test_count');

        $this->assertEquals(['test_count' => 2], $this->strategy->getMetrics());
    }

    #[Test]
    public function it_resets_warnings_and_metrics(): void
    {
        $this->strategy->addWarningPublic('Warning');
        $this->strategy->incrementMetricPublic('count');

        $this->strategy->reset();

        $this->assertEmpty($this->strategy->getWarnings());
        $this->assertEmpty($this->strategy->getMetrics());
    }
}

class ConcreteTestStrategy extends AbstractClassStrategy
{
    public function appliesTo(array $data): bool
    {
        return true;
    }

    public function enhance(array $data): array
    {
        return $data;
    }

    public function addWarningPublic(string $message): void
    {
        $this->addWarning($message);
    }

    public function incrementMetricPublic(string $key): void
    {
        $this->incrementMetric($key);
    }
}
