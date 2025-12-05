<?php

namespace Tests\Unit\Concerns;

use App\Services\Concerns\TracksMetricsAndWarnings;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('unit-pure')]
class TracksMetricsAndWarningsTest extends TestCase
{
    private object $subject;

    protected function setUp(): void
    {
        parent::setUp();

        // Create anonymous class using the trait
        $this->subject = new class
        {
            use TracksMetricsAndWarnings;

            // Expose protected methods for testing
            public function test_add_warning(string $message): void
            {
                $this->addWarning($message);
            }

            public function test_increment_metric(string $key, int $amount = 1): void
            {
                $this->incrementMetric($key, $amount);
            }

            public function test_set_metric(string $key, mixed $value): void
            {
                $this->setMetric($key, $value);
            }
        };
    }

    #[Test]
    public function it_initializes_with_empty_warnings_and_metrics(): void
    {
        $this->assertEmpty($this->subject->getWarnings());
        $this->assertEmpty($this->subject->getMetrics());
    }

    #[Test]
    public function it_can_add_warnings(): void
    {
        $this->subject->test_add_warning('First warning');
        $this->subject->test_add_warning('Second warning');

        $warnings = $this->subject->getWarnings();
        $this->assertCount(2, $warnings);
        $this->assertEquals('First warning', $warnings[0]);
        $this->assertEquals('Second warning', $warnings[1]);
    }

    #[Test]
    public function it_can_increment_metrics(): void
    {
        $this->subject->test_increment_metric('count');
        $this->subject->test_increment_metric('count');
        $this->subject->test_increment_metric('count', 5);

        $metrics = $this->subject->getMetrics();
        $this->assertEquals(7, $metrics['count']);
    }

    #[Test]
    public function it_can_set_metric_values(): void
    {
        $this->subject->test_set_metric('name', 'test');
        $this->subject->test_set_metric('active', true);
        $this->subject->test_set_metric('tags', ['a', 'b']);

        $metrics = $this->subject->getMetrics();
        $this->assertEquals('test', $metrics['name']);
        $this->assertTrue($metrics['active']);
        $this->assertEquals(['a', 'b'], $metrics['tags']);
    }

    #[Test]
    public function it_can_reset_warnings_and_metrics(): void
    {
        $this->subject->test_add_warning('Warning');
        $this->subject->test_increment_metric('count');
        $this->subject->test_set_metric('name', 'value');

        $this->assertNotEmpty($this->subject->getWarnings());
        $this->assertNotEmpty($this->subject->getMetrics());

        $this->subject->reset();

        $this->assertEmpty($this->subject->getWarnings());
        $this->assertEmpty($this->subject->getMetrics());
    }

    #[Test]
    public function it_handles_separate_metric_keys_independently(): void
    {
        $this->subject->test_increment_metric('apples');
        $this->subject->test_increment_metric('oranges', 3);
        $this->subject->test_increment_metric('apples', 2);

        $metrics = $this->subject->getMetrics();
        $this->assertEquals(3, $metrics['apples']);
        $this->assertEquals(3, $metrics['oranges']);
    }
}
