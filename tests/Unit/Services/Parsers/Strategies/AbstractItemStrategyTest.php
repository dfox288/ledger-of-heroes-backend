<?php

namespace Tests\Unit\Services\Parsers\Strategies;

use App\Services\Parsers\Strategies\AbstractItemStrategy;
use PHPUnit\Framework\Attributes\Test;
use SimpleXMLElement;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('unit-pure')]

class AbstractItemStrategyTest extends TestCase
{
    private TestItemStrategy $strategy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->strategy = new TestItemStrategy;
    }

    #[Test]
    public function it_initializes_with_empty_warnings_and_metrics()
    {
        $metadata = $this->strategy->extractMetadata();

        $this->assertArrayHasKey('warnings', $metadata);
        $this->assertArrayHasKey('metrics', $metadata);
        $this->assertEmpty($metadata['warnings']);
        $this->assertEmpty($metadata['metrics']);
    }

    #[Test]
    public function it_can_add_warnings()
    {
        $this->strategy->testAddWarning('Test warning 1');
        $this->strategy->testAddWarning('Test warning 2');

        $metadata = $this->strategy->extractMetadata();

        $this->assertCount(2, $metadata['warnings']);
        $this->assertContains('Test warning 1', $metadata['warnings']);
        $this->assertContains('Test warning 2', $metadata['warnings']);
    }

    #[Test]
    public function it_can_increment_metrics()
    {
        $this->strategy->testIncrementMetric('items_processed');
        $this->strategy->testIncrementMetric('items_processed');
        $this->strategy->testIncrementMetric('spells_found', 3);

        $metadata = $this->strategy->extractMetadata();

        $this->assertEquals(2, $metadata['metrics']['items_processed']);
        $this->assertEquals(3, $metadata['metrics']['spells_found']);
    }

    #[Test]
    public function it_can_set_metric_values()
    {
        $this->strategy->testSetMetric('total_items', 42);
        $this->strategy->testSetMetric('processing_time', 1.5);

        $metadata = $this->strategy->extractMetadata();

        $this->assertEquals(42, $metadata['metrics']['total_items']);
        $this->assertEquals(1.5, $metadata['metrics']['processing_time']);
    }

    #[Test]
    public function it_can_reset_warnings_and_metrics()
    {
        $this->strategy->testAddWarning('Test warning');
        $this->strategy->testIncrementMetric('counter');

        $this->strategy->reset();

        $metadata = $this->strategy->extractMetadata();

        $this->assertEmpty($metadata['warnings']);
        $this->assertEmpty($metadata['metrics']);
    }

    #[Test]
    public function it_returns_unmodified_modifiers_by_default()
    {
        $modifiers = [['modifier_category' => 'ac_base', 'value' => '10']];
        $baseData = ['name' => 'Test Item'];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $result = $this->strategy->enhanceModifiers($modifiers, $baseData, $xml);

        $this->assertEquals($modifiers, $result);
    }

    #[Test]
    public function it_returns_unmodified_abilities_by_default()
    {
        $abilities = [['ability_score_code' => 'STR', 'value' => '2']];
        $baseData = ['name' => 'Test Item'];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $result = $this->strategy->enhanceAbilities($abilities, $baseData, $xml);

        $this->assertEquals($abilities, $result);
    }

    #[Test]
    public function it_returns_empty_relationships_by_default()
    {
        $baseData = ['name' => 'Test Item'];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $result = $this->strategy->enhanceRelationships($baseData, $xml);

        $this->assertEquals([], $result);
    }
}

/**
 * Concrete test implementation of AbstractItemStrategy for testing.
 */
class TestItemStrategy extends AbstractItemStrategy
{
    public function appliesTo(array $baseData, SimpleXMLElement $xml): bool
    {
        return true;
    }

    // Expose protected methods for testing
    public function testAddWarning(string $message): void
    {
        $this->addWarning($message);
    }

    public function testIncrementMetric(string $key, int $amount = 1): void
    {
        $this->incrementMetric($key, $amount);
    }

    public function testSetMetric(string $key, mixed $value): void
    {
        $this->setMetric($key, $value);
    }
}
