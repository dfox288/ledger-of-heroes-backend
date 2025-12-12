# Factory Patterns for Testing

## Factory Location

All factories live in `database/factories/` and follow the naming convention `{Model}Factory.php`.

## make() vs create()

```php
// make() - Creates model instance WITHOUT saving to database
// Use when: You only need the object, not persistence
$spell = Spell::factory()->make();
expect($spell->name)->not->toBeNull();

// create() - Creates AND saves to database
// Use when: You need the model to exist in DB (relationships, queries)
$spell = Spell::factory()->create();
$response = $this->getJson("/api/v1/spells/{$spell->slug}");
```

**Rule of thumb:** Use `make()` by default, upgrade to `create()` only when needed.

## State Methods

Define reusable states for common scenarios:

```php
// In SpellFactory.php
public function cantrip(): static
{
    return $this->state(fn (array $attributes) => [
        'level' => 0,
    ]);
}

public function concentration(): static
{
    return $this->state(fn (array $attributes) => [
        'needs_concentration' => true,
        'duration' => 'Concentration, up to 1 minute',
    ]);
}

// Usage in tests
$cantrip = Spell::factory()->cantrip()->create();
$concentrationSpell = Spell::factory()->concentration()->create();
```

## Relationships

### BelongsTo (required relationship)
```php
// Factory handles it - SpellSchool is created automatically
$spell = Spell::factory()->create();
expect($spell->spellSchool)->not->toBeNull();

// Or specify explicitly
$school = SpellSchool::factory()->create(['name' => 'Evocation']);
$spell = Spell::factory()->create(['spell_school_id' => $school->id]);
```

### HasMany / BelongsToMany
```php
// Create spell with related effects
$spell = Spell::factory()
    ->has(SpellEffect::factory()->count(3), 'effects')
    ->create();

// Attach existing models
$classes = CharacterClass::factory()->count(2)->create();
$spell = Spell::factory()->create();
$spell->classes()->attach($classes);
```

## Test Data Slugs

**Always prefix test data slugs** to distinguish from real imported data:

```php
// In factory definition
return [
    'slug' => 'test:' . Str::slug($name),  // Creates: test:fireball
    // ...
];
```

This prevents collisions with imported data and makes cleanup easy.

## Common Patterns

### Testing Filters
```php
it('filters spells by level', function () {
    Spell::factory()->create(['level' => 1]);
    Spell::factory()->create(['level' => 3]);
    Spell::factory()->create(['level' => 3]);

    $response = $this->getJson('/api/v1/spells?filter=level=3');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(2);
});
```

### Testing Validation
```php
it('rejects invalid level', function () {
    $response = $this->getJson('/api/v1/spells?filter=level=invalid');

    $response->assertStatus(422);
});
```

### Testing Edge Cases
```php
it('handles spell with no material components', function () {
    $spell = Spell::factory()->create(['material_components' => null]);

    $response = $this->getJson("/api/v1/spells/{$spell->slug}");

    $response->assertOk();
    expect($response->json('data.material_components'))->toBeNull();
});
```

## Anti-Patterns

### Creating Too Much Data
```php
// ❌ WRONG - Creates unnecessary data
Spell::factory()->count(100)->create();
// Test only checks first result

// ✅ CORRECT - Create only what you need
Spell::factory()->count(3)->create();
```

### Hardcoding IDs
```php
// ❌ WRONG - Assumes ID exists
$spell = Spell::factory()->create(['spell_school_id' => 1]);

// ✅ CORRECT - Create or fetch the relationship
$school = SpellSchool::firstOrCreate(['code' => 'EV']);
$spell = Spell::factory()->create(['spell_school_id' => $school->id]);
```

### Not Using States
```php
// ❌ WRONG - Repeated inline state
Spell::factory()->create(['level' => 0, 'is_cantrip' => true]);
Spell::factory()->create(['level' => 0, 'is_cantrip' => true]);

// ✅ CORRECT - Use factory state
Spell::factory()->cantrip()->create();
Spell::factory()->cantrip()->create();
```
