# TDD Mandate

**THIS IS NON-NEGOTIABLE.**

## Rejection Criteria

Your work will be **REJECTED** if:
- Implementation code written before tests
- Tests skipped ("it's simple")
- Tests promised "later"
- Tests written after implementation
- "Manual testing is enough"

## Common Violations (REJECTED)

### "I'll add tests after"
```
❌ Wrote SpellService::calculateDamage()
❌ Then wrote SpellServiceTest
✅ Should have written failing test FIRST
```

### "It's just a small change"
```
❌ Added ->withTrashed() to query without test
❌ Changed validation rule without test
✅ Every behavior change needs a test proving it works
```

### "The existing tests pass"
```
❌ Tests pass but code has a bug
❌ Assumed passing tests = correct behavior
✅ If code is wrong but tests pass, tests are wrong - fix tests first
```

### "I'll refactor the tests later"
```
❌ Copied test with minor changes
❌ Created test that doesn't actually test the new behavior
✅ Each test must clearly verify specific behavior
```

## Pest Syntax

```php
// Pest uses a functional syntax (not PHPUnit classes)
it('creates a record', function () {
    $record = Record::factory()->create();
    expect($record)->toBeInstanceOf(Record::class);
});

test('user can view spells', function () {
    $response = $this->getJson('/api/v1/spells');
    $response->assertOk();
});

// Group related tests
describe('spell filtering', function () {
    it('filters by level', function () { /* ... */ });
    it('filters by school', function () { /* ... */ });
});
```

## Pest Expectations

```php
expect($value)->toBe($expected);           // Strict equality
expect($value)->toEqual($expected);        // Loose equality
expect($collection)->toHaveCount(5);
expect($response)->toBeInstanceOf(Response::class);
expect($array)->toContain('item');
expect($string)->toMatch('/pattern/');
```

## Running with Coverage

**Prerequisites:** XDebug 3.0+ or PCOV must be installed.

```bash
# Basic coverage report
docker compose exec php ./vendor/bin/pest --coverage

# Enforce minimum coverage threshold
docker compose exec php ./vendor/bin/pest --coverage --min=80

# Generate HTML coverage report
docker compose exec php ./vendor/bin/pest --coverage-html=coverage/html

# Generate Clover XML (for CI tools)
docker compose exec php ./vendor/bin/pest --coverage-clover=coverage/clover.xml
```

**Exclude untestable code:**
```php
// @codeCoverageIgnoreStart
// untestable code here
// @codeCoverageIgnoreEnd
```
