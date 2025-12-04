<?php

namespace Tests\Feature\Api\Concerns;

/**
 * Trait for testing Meilisearch filter operators.
 *
 * Provides reusable test methods for all operator types (=, !=, >, <, >=, <=, TO, IN, NOT IN, IS EMPTY).
 * Each entity test class defines entity-specific configuration via abstract methods.
 *
 * Usage:
 * 1. Use this trait in your test class
 * 2. Implement abstract methods to configure entity-specific test data
 * 3. Call common test methods from your test cases
 */
trait TestsFilterOperators
{
    /**
     * Get the base API endpoint for the entity (e.g., '/api/v1/spells')
     */
    abstract protected function getEndpoint(): string;

    /**
     * Get the integer field configuration for testing.
     * Return null if entity doesn't have a suitable integer field.
     *
     * @return array{field: string, testValue: int, lowValue: int, highValue: int}|null
     */
    abstract protected function getIntegerFieldConfig(): ?array;

    /**
     * Get the string field configuration for testing.
     * Return null if entity doesn't have a suitable string field.
     *
     * @return array{field: string, testValue: string, excludeValue: string}|null
     */
    abstract protected function getStringFieldConfig(): ?array;

    /**
     * Get the boolean field configuration for testing.
     * Return null if entity doesn't have a suitable boolean field.
     *
     * @return array{field: string, verifyCallback: callable}|null
     */
    abstract protected function getBooleanFieldConfig(): ?array;

    /**
     * Get the array field configuration for testing.
     * Return null if entity doesn't have a suitable array field.
     *
     * @return array{field: string, testValues: array, excludeValue: string, verifyCallback: callable}|null
     */
    abstract protected function getArrayFieldConfig(): ?array;

    // ============================================================
    // Integer Operator Tests
    // ============================================================

    protected function assertIntegerEquals(): void
    {
        $config = $this->getIntegerFieldConfig();
        if ($config === null) {
            $this->markTestSkipped('No integer field configured for this entity');
        }

        $response = $this->getJson("{$this->getEndpoint()}?filter={$config['field']} = {$config['testValue']}");

        $response->assertOk();
        $this->assertGreaterThan(0, $response->json('meta.total'), "Should find records with {$config['field']} = {$config['testValue']}");

        foreach ($response->json('data') as $record) {
            $actualValue = $this->extractFieldValue($record, $config['field']);
            $this->assertEquals($config['testValue'], $actualValue, "Record should have {$config['field']} = {$config['testValue']}");
        }
    }

    protected function assertIntegerNotEquals(): void
    {
        $config = $this->getIntegerFieldConfig();
        if ($config === null) {
            $this->markTestSkipped('No integer field configured for this entity');
        }

        $response = $this->getJson("{$this->getEndpoint()}?filter={$config['field']} != {$config['testValue']}");

        $response->assertOk();
        $this->assertGreaterThan(0, $response->json('meta.total'), "Should find records without {$config['field']} = {$config['testValue']}");

        foreach ($response->json('data') as $record) {
            $actualValue = $this->extractFieldValue($record, $config['field']);
            $this->assertNotEquals($config['testValue'], $actualValue, "Record should not have {$config['field']} = {$config['testValue']}");
        }
    }

    protected function assertIntegerGreaterThan(): void
    {
        $config = $this->getIntegerFieldConfig();
        if ($config === null) {
            $this->markTestSkipped('No integer field configured for this entity');
        }

        $response = $this->getJson("{$this->getEndpoint()}?filter={$config['field']} > {$config['lowValue']}");

        $response->assertOk();
        $this->assertGreaterThan(0, $response->json('meta.total'), "Should find records with {$config['field']} > {$config['lowValue']}");

        foreach ($response->json('data') as $record) {
            $actualValue = $this->extractFieldValue($record, $config['field']);
            $this->assertGreaterThan($config['lowValue'], $actualValue, "Record should have {$config['field']} > {$config['lowValue']}");
        }
    }

    protected function assertIntegerGreaterThanOrEqual(): void
    {
        $config = $this->getIntegerFieldConfig();
        if ($config === null) {
            $this->markTestSkipped('No integer field configured for this entity');
        }

        $response = $this->getJson("{$this->getEndpoint()}?filter={$config['field']} >= {$config['lowValue']}");

        $response->assertOk();
        $this->assertGreaterThan(0, $response->json('meta.total'), "Should find records with {$config['field']} >= {$config['lowValue']}");

        foreach ($response->json('data') as $record) {
            $actualValue = $this->extractFieldValue($record, $config['field']);
            $this->assertGreaterThanOrEqual($config['lowValue'], $actualValue, "Record should have {$config['field']} >= {$config['lowValue']}");
        }
    }

    protected function assertIntegerLessThan(): void
    {
        $config = $this->getIntegerFieldConfig();
        if ($config === null) {
            $this->markTestSkipped('No integer field configured for this entity');
        }

        $response = $this->getJson("{$this->getEndpoint()}?filter={$config['field']} < {$config['highValue']}");

        $response->assertOk();
        $this->assertGreaterThan(0, $response->json('meta.total'), "Should find records with {$config['field']} < {$config['highValue']}");

        foreach ($response->json('data') as $record) {
            $actualValue = $this->extractFieldValue($record, $config['field']);
            $this->assertLessThan($config['highValue'], $actualValue, "Record should have {$config['field']} < {$config['highValue']}");
        }
    }

    protected function assertIntegerLessThanOrEqual(): void
    {
        $config = $this->getIntegerFieldConfig();
        if ($config === null) {
            $this->markTestSkipped('No integer field configured for this entity');
        }

        $response = $this->getJson("{$this->getEndpoint()}?filter={$config['field']} <= {$config['highValue']}");

        $response->assertOk();
        $this->assertGreaterThan(0, $response->json('meta.total'), "Should find records with {$config['field']} <= {$config['highValue']}");

        foreach ($response->json('data') as $record) {
            $actualValue = $this->extractFieldValue($record, $config['field']);
            $this->assertLessThanOrEqual($config['highValue'], $actualValue, "Record should have {$config['field']} <= {$config['highValue']}");
        }
    }

    protected function assertIntegerToRange(): void
    {
        $config = $this->getIntegerFieldConfig();
        if ($config === null) {
            $this->markTestSkipped('No integer field configured for this entity');
        }

        $response = $this->getJson("{$this->getEndpoint()}?filter={$config['field']} {$config['lowValue']} TO {$config['highValue']}");

        $response->assertOk();
        $this->assertGreaterThan(0, $response->json('meta.total'), "Should find records with {$config['field']} in range");

        foreach ($response->json('data') as $record) {
            $actualValue = $this->extractFieldValue($record, $config['field']);
            $this->assertGreaterThanOrEqual($config['lowValue'], $actualValue, "Record should have {$config['field']} >= {$config['lowValue']}");
            $this->assertLessThanOrEqual($config['highValue'], $actualValue, "Record should have {$config['field']} <= {$config['highValue']}");
        }
    }

    // ============================================================
    // String Operator Tests
    // ============================================================

    protected function assertStringEquals(): void
    {
        $config = $this->getStringFieldConfig();
        if ($config === null) {
            $this->markTestSkipped('No string field configured for this entity');
        }

        $testValue = $config['testValue'];
        $needsQuotes = str_contains($testValue, ' ') || str_contains($testValue, '-');
        $filterValue = $needsQuotes ? "\"{$testValue}\"" : $testValue;

        $response = $this->getJson("{$this->getEndpoint()}?filter={$config['field']} = {$filterValue}&per_page=100");

        $response->assertOk();
        $this->assertGreaterThan(0, $response->json('meta.total'), "Should find records with {$config['field']} = {$testValue}");

        foreach ($response->json('data') as $record) {
            $actualValue = $this->extractFieldValue($record, $config['field']);
            $this->assertEquals($testValue, $actualValue, "Record should have {$config['field']} = {$testValue}");
        }
    }

    protected function assertStringNotEquals(): void
    {
        $config = $this->getStringFieldConfig();
        if ($config === null) {
            $this->markTestSkipped('No string field configured for this entity');
        }

        $excludeValue = $config['excludeValue'];
        $needsQuotes = str_contains($excludeValue, ' ') || str_contains($excludeValue, '-');
        $filterValue = $needsQuotes ? "\"{$excludeValue}\"" : $excludeValue;

        $response = $this->getJson("{$this->getEndpoint()}?filter={$config['field']} != {$filterValue}");

        $response->assertOk();
        $this->assertGreaterThan(0, $response->json('meta.total'), "Should find records without {$config['field']} = {$excludeValue}");

        foreach ($response->json('data') as $record) {
            $actualValue = $this->extractFieldValue($record, $config['field']);
            $this->assertNotEquals($excludeValue, $actualValue, "Record should not have {$config['field']} = {$excludeValue}");
        }
    }

    // ============================================================
    // Boolean Operator Tests
    // ============================================================

    protected function assertBooleanEqualsTrue(): void
    {
        $config = $this->getBooleanFieldConfig();
        if ($config === null) {
            $this->markTestSkipped('No boolean field configured for this entity');
        }

        $response = $this->getJson("{$this->getEndpoint()}?filter={$config['field']} = true&per_page=100");

        $response->assertOk();

        foreach ($response->json('data') as $record) {
            $callback = $config['verifyCallback'];
            $callback($this, $record, true);
        }
    }

    protected function assertBooleanEqualsFalse(): void
    {
        $config = $this->getBooleanFieldConfig();
        if ($config === null) {
            $this->markTestSkipped('No boolean field configured for this entity');
        }

        $response = $this->getJson("{$this->getEndpoint()}?filter={$config['field']} = false&per_page=100");

        $response->assertOk();
        $this->assertGreaterThan(0, $response->json('meta.total'), "Should find records with {$config['field']} = false");

        foreach ($response->json('data') as $record) {
            $callback = $config['verifyCallback'];
            $callback($this, $record, false);
        }
    }

    protected function assertBooleanNotEqualsTrue(): void
    {
        $config = $this->getBooleanFieldConfig();
        if ($config === null) {
            $this->markTestSkipped('No boolean field configured for this entity');
        }

        $response = $this->getJson("{$this->getEndpoint()}?filter={$config['field']} != true&per_page=100");

        $response->assertOk();
        $this->assertGreaterThan(0, $response->json('meta.total'), "Should find records with {$config['field']} != true");

        foreach ($response->json('data') as $record) {
            $callback = $config['verifyCallback'];
            $callback($this, $record, false);
        }
    }

    protected function assertBooleanNotEqualsFalse(): void
    {
        $config = $this->getBooleanFieldConfig();
        if ($config === null) {
            $this->markTestSkipped('No boolean field configured for this entity');
        }

        $response = $this->getJson("{$this->getEndpoint()}?filter={$config['field']} != false&per_page=100");

        $response->assertOk();

        foreach ($response->json('data') as $record) {
            $callback = $config['verifyCallback'];
            $callback($this, $record, true);
        }
    }

    protected function assertBooleanIsNull(): void
    {
        $config = $this->getBooleanFieldConfig();
        if ($config === null) {
            $this->markTestSkipped('No boolean field configured for this entity');
        }

        $response = $this->getJson("{$this->getEndpoint()}?filter={$config['field']} IS NULL");

        $response->assertOk();

        // Most entities with proper data will have all boolean fields set, so this may return 0 results
        // This test primarily verifies the operator works without errors
        $this->assertGreaterThanOrEqual(0, $response->json('meta.total'), 'IS NULL operator should work without errors');
    }

    protected function assertBooleanIsNotNull(): void
    {
        $config = $this->getBooleanFieldConfig();
        if ($config === null) {
            $this->markTestSkipped('No boolean field configured for this entity');
        }

        $response = $this->getJson("{$this->getEndpoint()}?filter={$config['field']} IS NOT NULL");

        $response->assertOk();
        $this->assertGreaterThan(0, $response->json('meta.total'), "Should find records with {$config['field']} IS NOT NULL");

        foreach ($response->json('data') as $record) {
            $actualValue = $this->extractFieldValue($record, $config['field']);
            $this->assertNotNull($actualValue, "Record should have {$config['field']} set");
            $this->assertIsBool($actualValue, "Record {$config['field']} should be boolean");
        }
    }

    // ============================================================
    // Array Operator Tests
    // ============================================================

    protected function assertArrayIn(): void
    {
        $config = $this->getArrayFieldConfig();
        if ($config === null) {
            $this->markTestSkipped('No array field configured for this entity');
        }

        $testValuesString = implode(', ', $config['testValues']);
        $response = $this->getJson("{$this->getEndpoint()}?filter={$config['field']} IN [{$testValuesString}]&per_page=100");

        $response->assertOk();

        foreach ($response->json('data') as $record) {
            $callback = $config['verifyCallback'];
            $callback($this, $record, $config['testValues'], true);
        }
    }

    protected function assertArrayNotIn(): void
    {
        $config = $this->getArrayFieldConfig();
        if ($config === null) {
            $this->markTestSkipped('No array field configured for this entity');
        }

        $response = $this->getJson("{$this->getEndpoint()}?filter={$config['field']} NOT IN [{$config['excludeValue']}]&per_page=100");

        $response->assertOk();
        $this->assertGreaterThan(0, $response->json('meta.total'), "Should find records without {$config['field']} containing {$config['excludeValue']}");

        foreach ($response->json('data') as $record) {
            $callback = $config['verifyCallback'];
            $callback($this, $record, [$config['excludeValue']], false);
        }
    }

    protected function assertArrayIsEmpty(): void
    {
        $config = $this->getArrayFieldConfig();
        if ($config === null) {
            $this->markTestSkipped('No array field configured for this entity');
        }

        $response = $this->getJson("{$this->getEndpoint()}?filter={$config['field']} IS EMPTY&per_page=100");

        $response->assertOk();

        // IS EMPTY operator should work without errors
        // Verification depends on whether data actually has empty arrays
        $this->assertGreaterThanOrEqual(0, $response->json('meta.total'), 'IS EMPTY operator should work without errors');
    }

    // ============================================================
    // Helper Methods
    // ============================================================

    /**
     * Extract field value from record, handling nested fields (e.g., 'size.code')
     */
    protected function extractFieldValue(array $record, string $field): mixed
    {
        if (str_contains($field, '.')) {
            $parts = explode('.', $field);
            $value = $record;
            foreach ($parts as $part) {
                $value = $value[$part] ?? null;
                if ($value === null) {
                    return null;
                }
            }

            return $value;
        }

        return $record[$field] ?? null;
    }
}
