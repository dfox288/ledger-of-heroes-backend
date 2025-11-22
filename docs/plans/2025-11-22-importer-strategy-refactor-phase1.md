# Importer Strategy Refactor Phase 1 Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Refactor RaceImporter and ClassImporter to use Strategy Pattern for architectural consistency with Item/Monster importers.

**Architecture:** Extract type-specific logic (base/subrace/variant for races, base/subclass for classes) into composable strategies. Each strategy <100 lines, independently testable. Reduces RaceImporter from 347→180 lines (-48%), ClassImporter from 263→120 lines (-54%).

**Tech Stack:** Laravel 12, PHP 8.4, PHPUnit 11, Strategy Pattern, TDD

---

## Prerequisites

**Verify starting state:**
```bash
# All tests passing
php artisan test
# Expected: 1,141 tests passing

# Current line counts
wc -l app/Services/Importers/RaceImporter.php
# Expected: 347

wc -l app/Services/Importers/ClassImporter.php
# Expected: 263
```

---

## Task 1: AbstractRaceStrategy Base Class

**Files:**
- Create: `app/Services/Importers/Strategies/Race/AbstractRaceStrategy.php`
- Create: `tests/Unit/Strategies/Race/AbstractRaceStrategyTest.php`

**Step 1: Write failing tests for AbstractRaceStrategy**

Create test file:

```php
<?php

namespace Tests\Unit\Strategies\Race;

use App\Services\Importers\Strategies\Race\AbstractRaceStrategy;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AbstractRaceStrategyTest extends TestCase
{
    private ConcreteTestStrategy $strategy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->strategy = new ConcreteTestStrategy();
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

// Concrete test implementation to test abstract class
class ConcreteTestStrategy extends AbstractRaceStrategy
{
    public function appliesTo(array $data): bool
    {
        return true;
    }

    public function enhance(array $data): array
    {
        return $data;
    }

    // Expose protected methods for testing
    public function addWarningPublic(string $message): void
    {
        $this->addWarning($message);
    }

    public function incrementMetricPublic(string $key): void
    {
        $this->incrementMetric($key);
    }
}
```

**Step 2: Run tests to verify they fail**

```bash
php artisan test --filter=AbstractRaceStrategyTest
```

Expected: FAIL - "Class 'App\Services\Importers\Strategies\Race\AbstractRaceStrategy' not found"

**Step 3: Create AbstractRaceStrategy base class**

Create directory and file:

```php
<?php

namespace App\Services\Importers\Strategies\Race;

abstract class AbstractRaceStrategy
{
    protected array $warnings = [];
    protected array $metrics = [];

    /**
     * Determine if this strategy applies to the given race data.
     */
    abstract public function appliesTo(array $data): bool;

    /**
     * Enhance race data with strategy-specific logic.
     */
    abstract public function enhance(array $data): array;

    /**
     * Get warnings generated during enhancement.
     */
    public function getWarnings(): array
    {
        return $this->warnings;
    }

    /**
     * Get metrics tracked during enhancement.
     */
    public function getMetrics(): array
    {
        return $this->metrics;
    }

    /**
     * Reset warnings and metrics for next entity.
     */
    public function reset(): void
    {
        $this->warnings = [];
        $this->metrics = [];
    }

    /**
     * Add a warning message.
     */
    protected function addWarning(string $message): void
    {
        $this->warnings[] = $message;
    }

    /**
     * Increment a metric counter.
     */
    protected function incrementMetric(string $key): void
    {
        $this->metrics[$key] = ($this->metrics[$key] ?? 0) + 1;
    }
}
```

**Step 4: Run tests to verify they pass**

```bash
php artisan test --filter=AbstractRaceStrategyTest
```

Expected: PASS - 3 tests

**Step 5: Run full test suite**

```bash
php artisan test
```

Expected: 1,144 tests passing (+3)

**Step 6: Format code**

```bash
./vendor/bin/pint
```

**Step 7: Commit**

```bash
git add app/Services/Importers/Strategies/Race/AbstractRaceStrategy.php tests/Unit/Strategies/Race/AbstractRaceStrategyTest.php
git commit -m "feat: add AbstractRaceStrategy base class

Provides metadata tracking (warnings, metrics) and reset functionality
for race import strategies.

🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

## Task 2: BaseRaceStrategy

**Files:**
- Create: `app/Services/Importers/Strategies/Race/BaseRaceStrategy.php`
- Create: `tests/Unit/Strategies/Race/BaseRaceStrategyTest.php`
- Create: `tests/Fixtures/xml/races/base-race.xml`

**Step 1: Create test fixture (real XML)**

Create `tests/Fixtures/xml/races/base-race.xml`:

```xml
<?xml version='1.0' encoding='utf-8'?>
<compendium version="5">
    <race>
        <name>Elf</name>
        <size>M</size>
        <speed>30</speed>
        <ability>Dex 2</ability>
        <trait>
            <name>Darkvision</name>
            <text>You can see in dim light within 60 feet of you as if it were bright light.</text>
        </trait>
        <text>Elves are a magical people of otherworldly grace.</text>
    </race>
</compendium>
```

**Step 2: Write failing tests for BaseRaceStrategy**

```php
<?php

namespace Tests\Unit\Strategies\Race;

use App\Services\Importers\Strategies\Race\BaseRaceStrategy;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BaseRaceStrategyTest extends TestCase
{
    private BaseRaceStrategy $strategy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->strategy = new BaseRaceStrategy();
    }

    #[Test]
    public function it_applies_to_base_races(): void
    {
        $data = ['name' => 'Elf', 'base_race_name' => null];

        $this->assertTrue($this->strategy->appliesTo($data));
    }

    #[Test]
    public function it_does_not_apply_to_subraces(): void
    {
        $data = ['name' => 'High Elf', 'base_race_name' => 'Elf'];

        $this->assertFalse($this->strategy->appliesTo($data));
    }

    #[Test]
    public function it_does_not_apply_to_variants(): void
    {
        $data = ['name' => 'Dragonborn (Gold)', 'variant_of' => 'Dragonborn'];

        $this->assertFalse($this->strategy->appliesTo($data));
    }

    #[Test]
    public function it_sets_parent_race_id_to_null(): void
    {
        $data = ['name' => 'Elf', 'size_code' => 'M'];

        $result = $this->strategy->enhance($data);

        $this->assertNull($result['parent_race_id']);
    }

    #[Test]
    public function it_tracks_base_races_processed_metric(): void
    {
        $data = ['name' => 'Elf'];

        $this->strategy->enhance($data);

        $metrics = $this->strategy->getMetrics();
        $this->assertEquals(1, $metrics['base_races_processed']);
    }

    #[Test]
    public function it_warns_if_size_missing(): void
    {
        $data = ['name' => 'Elf', 'size_code' => null];

        $this->strategy->enhance($data);

        $warnings = $this->strategy->getWarnings();
        $this->assertCount(1, $warnings);
        $this->assertStringContainsString('size_code', $warnings[0]);
    }

    #[Test]
    public function it_warns_if_speed_missing(): void
    {
        $data = ['name' => 'Elf', 'size_code' => 'M', 'speed' => null];

        $this->strategy->enhance($data);

        $warnings = $this->strategy->getWarnings();
        $this->assertCount(1, $warnings);
        $this->assertStringContainsString('speed', $warnings[0]);
    }

    #[Test]
    public function it_does_not_modify_other_data(): void
    {
        $data = [
            'name' => 'Elf',
            'size_code' => 'M',
            'speed' => 30,
            'description' => 'Test description',
        ];

        $result = $this->strategy->enhance($data);

        $this->assertEquals('Elf', $result['name']);
        $this->assertEquals('M', $result['size_code']);
        $this->assertEquals(30, $result['speed']);
        $this->assertEquals('Test description', $result['description']);
    }
}
```

**Step 3: Run tests to verify they fail**

```bash
php artisan test --filter=BaseRaceStrategyTest
```

Expected: FAIL - "Class 'App\Services\Importers\Strategies\Race\BaseRaceStrategy' not found"

**Step 4: Implement BaseRaceStrategy**

```php
<?php

namespace App\Services\Importers\Strategies\Race;

class BaseRaceStrategy extends AbstractRaceStrategy
{
    /**
     * Base races have no parent and are not variants.
     */
    public function appliesTo(array $data): bool
    {
        return empty($data['base_race_name']) && empty($data['variant_of']);
    }

    /**
     * Enhance base race data with validation and metadata.
     */
    public function enhance(array $data): array
    {
        // Validate required fields
        if (empty($data['size_code'])) {
            $this->addWarning("Base race '{$data['name']}' missing size_code");
        }

        if (empty($data['speed'])) {
            $this->addWarning("Base race '{$data['name']}' missing speed");
        }

        // Set parent_race_id to null (this is a base race)
        $data['parent_race_id'] = null;

        // Track metric
        $this->incrementMetric('base_races_processed');

        return $data;
    }
}
```

**Step 5: Run tests to verify they pass**

```bash
php artisan test --filter=BaseRaceStrategyTest
```

Expected: PASS - 8 tests

**Step 6: Run full test suite**

```bash
php artisan test
```

Expected: 1,152 tests passing (+8)

**Step 7: Format code**

```bash
./vendor/bin/pint
```

**Step 8: Commit**

```bash
git add app/Services/Importers/Strategies/Race/BaseRaceStrategy.php tests/Unit/Strategies/Race/BaseRaceStrategyTest.php tests/Fixtures/xml/races/base-race.xml
git commit -m "feat: add BaseRaceStrategy for base races

Handles base races (Elf, Dwarf, Human) with validation and
parent_race_id = null setting.

🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

## Task 3: SubraceStrategy

**Files:**
- Create: `app/Services/Importers/Strategies/Race/SubraceStrategy.php`
- Create: `tests/Unit/Strategies/Race/SubraceStrategyTest.php`
- Create: `tests/Fixtures/xml/races/subrace.xml`

**Step 1: Create test fixture**

Create `tests/Fixtures/xml/races/subrace.xml`:

```xml
<?xml version='1.0' encoding='utf-8'?>
<compendium version="5">
    <race>
        <name>High Elf</name>
        <size>M</size>
        <speed>30</speed>
        <ability>Int 1</ability>
        <trait>
            <name>Elf Weapon Training</name>
            <text>You have proficiency with longswords and shortbows.</text>
        </trait>
        <text>Source: Player's Handbook p. 23</text>
        <modifier category="bonus">Intelligence +1</modifier>
    </race>
</compendium>
```

**Step 2: Write failing tests**

```php
<?php

namespace Tests\Unit\Strategies\Race;

use App\Models\Race;
use App\Models\Size;
use App\Services\Importers\Strategies\Race\SubraceStrategy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SubraceStrategyTest extends TestCase
{
    use RefreshDatabase;

    private SubraceStrategy $strategy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->strategy = new SubraceStrategy();

        // Seed required lookup data
        $this->seed(\Database\Seeders\SizeSeeder::class);
    }

    #[Test]
    public function it_applies_to_subraces(): void
    {
        $data = ['name' => 'High Elf', 'base_race_name' => 'Elf'];

        $this->assertTrue($this->strategy->appliesTo($data));
    }

    #[Test]
    public function it_does_not_apply_to_base_races(): void
    {
        $data = ['name' => 'Elf', 'base_race_name' => null];

        $this->assertFalse($this->strategy->appliesTo($data));
    }

    #[Test]
    public function it_does_not_apply_to_variants(): void
    {
        $data = ['name' => 'High Elf', 'base_race_name' => 'Elf', 'variant_of' => 'Elf'];

        $this->assertFalse($this->strategy->appliesTo($data));
    }

    #[Test]
    public function it_resolves_existing_base_race(): void
    {
        $size = Size::where('code', 'M')->first();
        $baseRace = Race::factory()->create([
            'name' => 'Elf',
            'slug' => 'elf',
            'size_id' => $size->id,
        ]);

        $data = [
            'name' => 'High Elf',
            'base_race_name' => 'Elf',
            'size_code' => 'M',
            'speed' => 30,
        ];

        $result = $this->strategy->enhance($data);

        $this->assertEquals($baseRace->id, $result['parent_race_id']);
    }

    #[Test]
    public function it_creates_stub_base_race_if_missing(): void
    {
        $this->assertDatabaseMissing('races', ['slug' => 'dwarf']);

        $data = [
            'name' => 'Mountain Dwarf',
            'base_race_name' => 'Dwarf',
            'size_code' => 'M',
            'speed' => 25,
        ];

        $result = $this->strategy->enhance($data);

        $this->assertDatabaseHas('races', [
            'slug' => 'dwarf',
            'name' => 'Dwarf',
        ]);

        $baseRace = Race::where('slug', 'dwarf')->first();
        $this->assertEquals($baseRace->id, $result['parent_race_id']);
    }

    #[Test]
    public function it_generates_compound_slug(): void
    {
        $data = [
            'name' => 'High Elf',
            'base_race_name' => 'Elf',
            'size_code' => 'M',
            'speed' => 30,
        ];

        $result = $this->strategy->enhance($data);

        $this->assertEquals('elf-high-elf', $result['slug']);
    }

    #[Test]
    public function it_tracks_subraces_processed_metric(): void
    {
        $data = [
            'name' => 'High Elf',
            'base_race_name' => 'Elf',
            'size_code' => 'M',
            'speed' => 30,
        ];

        $this->strategy->enhance($data);

        $metrics = $this->strategy->getMetrics();
        $this->assertEquals(1, $metrics['subraces_processed']);
    }

    #[Test]
    public function it_tracks_base_races_created_metric(): void
    {
        $data = [
            'name' => 'Mountain Dwarf',
            'base_race_name' => 'Dwarf',
            'size_code' => 'M',
            'speed' => 25,
        ];

        $this->strategy->enhance($data);

        $metrics = $this->strategy->getMetrics();
        $this->assertEquals(1, $metrics['base_races_created']);
    }

    #[Test]
    public function it_tracks_base_races_resolved_metric(): void
    {
        $size = Size::where('code', 'M')->first();
        Race::factory()->create(['name' => 'Elf', 'slug' => 'elf', 'size_id' => $size->id]);

        $data = [
            'name' => 'High Elf',
            'base_race_name' => 'Elf',
            'size_code' => 'M',
            'speed' => 30,
        ];

        $this->strategy->enhance($data);

        $metrics = $this->strategy->getMetrics();
        $this->assertEquals(1, $metrics['base_races_resolved']);
    }

    #[Test]
    public function it_warns_if_size_missing_for_stub_creation(): void
    {
        $data = [
            'name' => 'Mountain Dwarf',
            'base_race_name' => 'Dwarf',
            'size_code' => null,
            'speed' => 25,
        ];

        $this->strategy->enhance($data);

        $warnings = $this->strategy->getWarnings();
        $this->assertCount(1, $warnings);
        $this->assertStringContainsString('size_code', $warnings[0]);
    }
}
```

**Step 3: Run tests to verify they fail**

```bash
php artisan test --filter=SubraceStrategyTest
```

Expected: FAIL - "Class 'App\Services\Importers\Strategies\Race\SubraceStrategy' not found"

**Step 4: Implement SubraceStrategy**

```php
<?php

namespace App\Services\Importers\Strategies\Race;

use App\Models\Race;
use App\Models\Size;
use Illuminate\Support\Str;

class SubraceStrategy extends AbstractRaceStrategy
{
    /**
     * Subraces have a base_race_name but are not variants.
     */
    public function appliesTo(array $data): bool
    {
        return ! empty($data['base_race_name']) && empty($data['variant_of']);
    }

    /**
     * Enhance subrace data with parent resolution and compound slug.
     */
    public function enhance(array $data): array
    {
        $baseRaceName = $data['base_race_name'];
        $baseRaceSlug = Str::slug($baseRaceName);

        // Find or create base race
        $baseRace = Race::where('slug', $baseRaceSlug)->first();

        if (! $baseRace) {
            $baseRace = $this->createStubBaseRace($baseRaceName, $data);
            $this->incrementMetric('base_races_created');
        } else {
            $this->incrementMetric('base_races_resolved');
        }

        // Set parent_race_id
        $data['parent_race_id'] = $baseRace->id;

        // Generate compound slug (base-race-subrace)
        $data['slug'] = $baseRaceSlug.'-'.Str::slug($data['name']);

        // Track metric
        $this->incrementMetric('subraces_processed');

        return $data;
    }

    /**
     * Create a minimal stub base race when referenced by subrace.
     */
    private function createStubBaseRace(string $name, array $subraceData): Race
    {
        if (empty($subraceData['size_code'])) {
            $this->addWarning("Cannot create stub base race '{$name}': subrace missing size_code");

            return Race::factory()->make(['id' => 0]); // Return invalid stub
        }

        $size = Size::where('code', $subraceData['size_code'])->first();

        return Race::create([
            'name' => $name,
            'slug' => Str::slug($name),
            'size_id' => $size->id,
            'speed' => $subraceData['speed'] ?? 30,
            'description' => "Base race (auto-created from subrace '{$subraceData['name']}')",
        ]);
    }
}
```

**Step 5: Run tests to verify they pass**

```bash
php artisan test --filter=SubraceStrategyTest
```

Expected: PASS - 11 tests

**Step 6: Run full test suite**

```bash
php artisan test
```

Expected: 1,163 tests passing (+11)

**Step 7: Format code**

```bash
./vendor/bin/pint
```

**Step 8: Commit**

```bash
git add app/Services/Importers/Strategies/Race/SubraceStrategy.php tests/Unit/Strategies/Race/SubraceStrategyTest.php tests/Fixtures/xml/races/subrace.xml
git commit -m "feat: add SubraceStrategy for subraces

Handles subraces (High Elf, Mountain Dwarf) with parent resolution,
stub base race creation, and compound slug generation.

🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

## Task 4: RacialVariantStrategy

**Files:**
- Create: `app/Services/Importers/Strategies/Race/RacialVariantStrategy.php`
- Create: `tests/Unit/Strategies/Race/RacialVariantStrategyTest.php`
- Create: `tests/Fixtures/xml/races/variant.xml`

**Step 1: Create test fixture**

Create `tests/Fixtures/xml/races/variant.xml`:

```xml
<?xml version='1.0' encoding='utf-8'?>
<compendium version="5">
    <race>
        <name>Dragonborn (Gold)</name>
        <size>M</size>
        <speed>30</speed>
        <ability>Str 2, Cha 1</ability>
        <trait>
            <name>Draconic Ancestry</name>
            <text>Gold. Damage Type: Fire. Breath Weapon: 15 ft. cone (Dex. save)</text>
        </trait>
        <text>Your draconic ancestry determines your damage resistance and breath weapon.</text>
    </race>
</compendium>
```

**Step 2: Write failing tests**

```php
<?php

namespace Tests\Unit\Strategies\Race;

use App\Models\Race;
use App\Models\Size;
use App\Services\Importers\Strategies\Race\RacialVariantStrategy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RacialVariantStrategyTest extends TestCase
{
    use RefreshDatabase;

    private RacialVariantStrategy $strategy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->strategy = new RacialVariantStrategy();

        $this->seed(\Database\Seeders\SizeSeeder::class);
    }

    #[Test]
    public function it_applies_to_variants(): void
    {
        $data = ['name' => 'Dragonborn (Gold)', 'variant_of' => 'Dragonborn'];

        $this->assertTrue($this->strategy->appliesTo($data));
    }

    #[Test]
    public function it_does_not_apply_to_base_races(): void
    {
        $data = ['name' => 'Dragonborn', 'variant_of' => null];

        $this->assertFalse($this->strategy->appliesTo($data));
    }

    #[Test]
    public function it_parses_variant_type_from_name(): void
    {
        $data = ['name' => 'Dragonborn (Gold)', 'variant_of' => 'Dragonborn'];

        $result = $this->strategy->enhance($data);

        $this->assertEquals('Gold', $result['variant_type']);
    }

    #[Test]
    public function it_generates_variant_slug(): void
    {
        $data = ['name' => 'Dragonborn (Gold)', 'variant_of' => 'Dragonborn'];

        $result = $this->strategy->enhance($data);

        $this->assertEquals('dragonborn-gold', $result['slug']);
    }

    #[Test]
    public function it_resolves_parent_race(): void
    {
        $size = Size::where('code', 'M')->first();
        $parentRace = Race::factory()->create([
            'name' => 'Dragonborn',
            'slug' => 'dragonborn',
            'size_id' => $size->id,
        ]);

        $data = ['name' => 'Dragonborn (Gold)', 'variant_of' => 'Dragonborn'];

        $result = $this->strategy->enhance($data);

        $this->assertEquals($parentRace->id, $result['parent_race_id']);
    }

    #[Test]
    public function it_tracks_variants_processed_metric(): void
    {
        $data = ['name' => 'Dragonborn (Gold)', 'variant_of' => 'Dragonborn'];

        $this->strategy->enhance($data);

        $metrics = $this->strategy->getMetrics();
        $this->assertEquals(1, $metrics['variants_processed']);
    }

    #[Test]
    public function it_warns_if_parent_race_missing(): void
    {
        $data = ['name' => 'Dragonborn (Gold)', 'variant_of' => 'Dragonborn'];

        $this->strategy->enhance($data);

        $warnings = $this->strategy->getWarnings();
        $this->assertCount(1, $warnings);
        $this->assertStringContainsString('Parent race', $warnings[0]);
    }

    #[Test]
    public function it_handles_variant_without_parentheses(): void
    {
        $data = ['name' => 'Variant Dragonborn', 'variant_of' => 'Dragonborn'];

        $result = $this->strategy->enhance($data);

        $this->assertArrayNotHasKey('variant_type', $result);
        $this->assertEquals('variant-dragonborn', $result['slug']);
    }
}
```

**Step 3: Run tests to verify they fail**

```bash
php artisan test --filter=RacialVariantStrategyTest
```

Expected: FAIL - "Class 'App\Services\Importers\Strategies\Race\RacialVariantStrategy' not found"

**Step 4: Implement RacialVariantStrategy**

```php
<?php

namespace App\Services\Importers\Strategies\Race;

use App\Models\Race;
use Illuminate\Support\Str;

class RacialVariantStrategy extends AbstractRaceStrategy
{
    /**
     * Variants have a variant_of field.
     */
    public function appliesTo(array $data): bool
    {
        return ! empty($data['variant_of']);
    }

    /**
     * Enhance variant data with type extraction and parent resolution.
     */
    public function enhance(array $data): array
    {
        $variantOfName = $data['variant_of'];

        // Parse variant type from name: "Dragonborn (Gold)" → "Gold"
        if (preg_match('/\(([^)]+)\)/', $data['name'], $matches)) {
            $data['variant_type'] = $matches[1];

            // Generate slug from base + variant: dragonborn-gold
            $baseSlug = Str::slug($variantOfName);
            $variantTypeSlug = Str::slug($matches[1]);
            $data['slug'] = "{$baseSlug}-{$variantTypeSlug}";
        } else {
            // No variant type in parentheses, use full name
            $data['slug'] = Str::slug($data['name']);
        }

        // Resolve parent race
        $parentRace = Race::where('slug', Str::slug($variantOfName))->first();

        if ($parentRace) {
            $data['parent_race_id'] = $parentRace->id;
        } else {
            $this->addWarning("Parent race '{$variantOfName}' not found for variant '{$data['name']}'");
            $data['parent_race_id'] = null;
        }

        // Track metric
        $this->incrementMetric('variants_processed');

        return $data;
    }
}
```

**Step 5: Run tests to verify they pass**

```bash
php artisan test --filter=RacialVariantStrategyTest
```

Expected: PASS - 8 tests

**Step 6: Run full test suite**

```bash
php artisan test
```

Expected: 1,171 tests passing (+8)

**Step 7: Format code**

```bash
./vendor/bin/pint
```

**Step 8: Commit**

```bash
git add app/Services/Importers/Strategies/Race/RacialVariantStrategy.php tests/Unit/Strategies/Race/RacialVariantStrategyTest.php tests/Fixtures/xml/races/variant.xml
git commit -m "feat: add RacialVariantStrategy for race variants

Handles racial variants (Dragonborn colors, Tiefling bloodlines) with
variant type extraction and parent resolution.

🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

## Task 5: Refactor RaceImporter to Use Strategies

**Files:**
- Modify: `app/Services/Importers/RaceImporter.php`
- Modify: `tests/Feature/Importers/RaceImporterTest.php` (update existing tests)

**Step 1: Read current RaceImporter**

```bash
cat app/Services/Importers/RaceImporter.php
```

Review current implementation to understand integration points.

**Step 2: Create backup of current tests**

```bash
php artisan test --filter=RaceImporterTest
```

Expected: Existing tests pass - note the count

**Step 3: Refactor RaceImporter**

Modify `app/Services/Importers/RaceImporter.php`:

```php
<?php

namespace App\Services\Importers;

use App\Models\AbilityScore;
use App\Models\Modifier;
use App\Models\Race;
use App\Models\Size;
use App\Models\Source;
use App\Services\Importers\Concerns\ImportsConditions;
use App\Services\Importers\Concerns\ImportsEntitySpells;
use App\Services\Importers\Concerns\ImportsLanguages;
use App\Services\Importers\Concerns\ImportsModifiers;
use App\Services\Importers\Strategies\Race\BaseRaceStrategy;
use App\Services\Importers\Strategies\Race\RacialVariantStrategy;
use App\Services\Importers\Strategies\Race\SubraceStrategy;
use App\Services\Parsers\RaceXmlParser;
use Illuminate\Support\Facades\Log;

class RaceImporter extends BaseImporter
{
    use ImportsConditions;
    use ImportsEntitySpells;
    use ImportsLanguages;
    use ImportsModifiers;

    private array $strategies = [];

    public function __construct(RaceXmlParser $parser)
    {
        parent::__construct($parser);
        $this->initializeStrategies();
    }

    /**
     * Initialize race import strategies.
     */
    private function initializeStrategies(): void
    {
        $this->strategies = [
            new BaseRaceStrategy(),
            new SubraceStrategy(),
            new RacialVariantStrategy(),
        ];
    }

    protected function importEntity(array $raceData): Race
    {
        // Apply all applicable strategies
        foreach ($this->strategies as $strategy) {
            if ($strategy->appliesTo($raceData)) {
                $raceData = $strategy->enhance($raceData);
                $this->logStrategyApplication($strategy, $raceData);
            }
        }

        // Lookup size by code
        $size = Size::where('code', $raceData['size_code'])->firstOrFail();

        // If slug not set by strategy, generate from name
        if (! isset($raceData['slug'])) {
            $raceData['slug'] = $this->generateSlug($raceData['name']);
        }

        // Create or update race using slug as unique key
        $race = Race::updateOrCreate(
            ['slug' => $raceData['slug']],
            [
                'name' => $raceData['name'],
                'parent_race_id' => $raceData['parent_race_id'] ?? null,
                'size_id' => $size->id,
                'speed' => $raceData['speed'],
                'description' => $raceData['description'] ?? '',
            ]
        );

        // Import relationships using existing traits
        $this->importModifiers($race, $raceData['modifiers'] ?? []);
        $this->importLanguages($race, $raceData['languages'] ?? []);
        $this->importConditions($race, $raceData['condition_immunities'] ?? []);
        $this->importEntitySpells($race, $raceData['spell_references'] ?? []);
        $this->importSources($race, $raceData['sources'] ?? []);
        $this->importTraits($race, $raceData['traits'] ?? []);
        $this->importProficiencies($race, $raceData['proficiencies'] ?? []);

        return $race;
    }

    /**
     * Log strategy application to import-strategy channel.
     */
    private function logStrategyApplication($strategy, array $data): void
    {
        Log::channel('import-strategy')->info('Strategy applied', [
            'race' => $data['name'],
            'strategy' => class_basename($strategy),
            'warnings' => $strategy->getWarnings(),
            'metrics' => $strategy->getMetrics(),
        ]);

        // Reset strategy for next entity
        $strategy->reset();
    }
}
```

**Step 4: Run existing tests to check for regressions**

```bash
php artisan test --filter=RaceImporterTest
```

Expected: All existing tests should still pass

**Step 5: Run full test suite**

```bash
php artisan test
```

Expected: 1,171 tests passing (no change - refactor only)

**Step 6: Test actual import**

```bash
php artisan migrate:fresh --seed
php artisan import:races import-files/races-phb.xml
```

Expected: Successfully imports races with strategy logging

**Step 7: Verify race counts match previous imports**

```bash
php artisan tinker --execute="echo 'Races: ' . \App\Models\Race::count();"
```

Expected: Same count as before refactoring (~115 races from PHB)

**Step 8: Format code**

```bash
./vendor/bin/pint
```

**Step 9: Commit**

```bash
git add app/Services/Importers/RaceImporter.php
git commit -m "refactor: integrate strategy pattern into RaceImporter

Reduces RaceImporter from 347 to ~180 lines (-48%) by extracting
type-specific logic into composable strategies.

- BaseRaceStrategy handles base races
- SubraceStrategy handles subraces with parent resolution
- RacialVariantStrategy handles variants

All existing tests pass, no functional changes.

🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

## Task 6: AbstractClassStrategy Base Class

**Files:**
- Create: `app/Services/Importers/Strategies/CharacterClass/AbstractClassStrategy.php`
- Create: `tests/Unit/Strategies/CharacterClass/AbstractClassStrategyTest.php`

**Step 1: Write failing tests**

```php
<?php

namespace Tests\Unit\Strategies\CharacterClass;

use App\Services\Importers\Strategies\CharacterClass\AbstractClassStrategy;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AbstractClassStrategyTest extends TestCase
{
    private ConcreteTestStrategy $strategy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->strategy = new ConcreteTestStrategy();
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
```

**Step 2: Run tests to verify they fail**

```bash
php artisan test --filter=AbstractClassStrategyTest
```

Expected: FAIL - "Class not found"

**Step 3: Create AbstractClassStrategy**

```php
<?php

namespace App\Services\Importers\Strategies\CharacterClass;

abstract class AbstractClassStrategy
{
    protected array $warnings = [];
    protected array $metrics = [];

    abstract public function appliesTo(array $data): bool;

    abstract public function enhance(array $data): array;

    public function getWarnings(): array
    {
        return $this->warnings;
    }

    public function getMetrics(): array
    {
        return $this->metrics;
    }

    public function reset(): void
    {
        $this->warnings = [];
        $this->metrics = [];
    }

    protected function addWarning(string $message): void
    {
        $this->warnings[] = $message;
    }

    protected function incrementMetric(string $key): void
    {
        $this->metrics[$key] = ($this->metrics[$key] ?? 0) + 1;
    }
}
```

**Step 4: Run tests to verify they pass**

```bash
php artisan test --filter=AbstractClassStrategyTest
```

Expected: PASS - 3 tests

**Step 5: Run full test suite**

```bash
php artisan test
```

Expected: 1,174 tests passing (+3)

**Step 6: Format and commit**

```bash
./vendor/bin/pint
git add app/Services/Importers/Strategies/CharacterClass/AbstractClassStrategy.php tests/Unit/Strategies/CharacterClass/AbstractClassStrategyTest.php
git commit -m "feat: add AbstractClassStrategy base class

Provides metadata tracking for class import strategies.

🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

## Task 7: BaseClassStrategy

**Files:**
- Create: `app/Services/Importers/Strategies/CharacterClass/BaseClassStrategy.php`
- Create: `tests/Unit/Strategies/CharacterClass/BaseClassStrategyTest.php`

**Step 1: Write failing tests**

```php
<?php

namespace Tests\Unit\Strategies\CharacterClass;

use App\Models\AbilityScore;
use App\Services\Importers\Strategies\CharacterClass\BaseClassStrategy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BaseClassStrategyTest extends TestCase
{
    use RefreshDatabase;

    private BaseClassStrategy $strategy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->strategy = new BaseClassStrategy();

        $this->seed(\Database\Seeders\AbilityScoreSeeder::class);
    }

    #[Test]
    public function it_applies_to_base_classes(): void
    {
        $data = ['name' => 'Wizard', 'hit_die' => 6];

        $this->assertTrue($this->strategy->appliesTo($data));
    }

    #[Test]
    public function it_does_not_apply_to_subclasses(): void
    {
        $data = ['name' => 'School of Evocation', 'hit_die' => 0];

        $this->assertFalse($this->strategy->appliesTo($data));
    }

    #[Test]
    public function it_sets_parent_class_id_to_null(): void
    {
        $data = ['name' => 'Wizard', 'hit_die' => 6];

        $result = $this->strategy->enhance($data);

        $this->assertNull($result['parent_class_id']);
    }

    #[Test]
    public function it_resolves_spellcasting_ability_id(): void
    {
        $data = [
            'name' => 'Wizard',
            'hit_die' => 6,
            'spellcasting_ability' => 'Intelligence',
        ];

        $result = $this->strategy->enhance($data);

        $intelligence = AbilityScore::where('name', 'Intelligence')->first();
        $this->assertEquals($intelligence->id, $result['spellcasting_ability_id']);
    }

    #[Test]
    public function it_tracks_spellcasters_metric(): void
    {
        $data = [
            'name' => 'Wizard',
            'hit_die' => 6,
            'spellcasting_ability' => 'Intelligence',
        ];

        $this->strategy->enhance($data);

        $metrics = $this->strategy->getMetrics();
        $this->assertEquals(1, $metrics['spellcasters_detected']);
    }

    #[Test]
    public function it_tracks_martial_classes_metric(): void
    {
        $data = ['name' => 'Fighter', 'hit_die' => 10];

        $this->strategy->enhance($data);

        $metrics = $this->strategy->getMetrics();
        $this->assertEquals(1, $metrics['martial_classes']);
    }

    #[Test]
    public function it_warns_if_hit_die_missing(): void
    {
        $data = ['name' => 'Wizard', 'hit_die' => null];

        $this->strategy->enhance($data);

        $warnings = $this->strategy->getWarnings();
        $this->assertCount(1, $warnings);
        $this->assertStringContainsString('hit_die', $warnings[0]);
    }

    #[Test]
    public function it_tracks_base_classes_processed_metric(): void
    {
        $data = ['name' => 'Wizard', 'hit_die' => 6];

        $this->strategy->enhance($data);

        $metrics = $this->strategy->getMetrics();
        $this->assertEquals(1, $metrics['base_classes_processed']);
    }
}
```

**Step 2: Run tests to verify they fail**

```bash
php artisan test --filter=BaseClassStrategyTest
```

Expected: FAIL - "Class not found"

**Step 3: Implement BaseClassStrategy**

```php
<?php

namespace App\Services\Importers\Strategies\CharacterClass;

use App\Models\AbilityScore;

class BaseClassStrategy extends AbstractClassStrategy
{
    /**
     * Base classes have hit_die > 0.
     */
    public function appliesTo(array $data): bool
    {
        return ($data['hit_die'] ?? 0) > 0;
    }

    /**
     * Enhance base class data with spellcasting detection and validation.
     */
    public function enhance(array $data): array
    {
        // Validate required fields
        if (empty($data['hit_die'])) {
            $this->addWarning("Base class '{$data['name']}' missing hit_die");
        }

        // Set parent_class_id to null (this is a base class)
        $data['parent_class_id'] = null;

        // Resolve spellcasting ability if present
        if (! empty($data['spellcasting_ability'])) {
            $ability = AbilityScore::where('name', $data['spellcasting_ability'])->first();
            $data['spellcasting_ability_id'] = $ability?->id;

            $this->incrementMetric('spellcasters_detected');
        } else {
            $data['spellcasting_ability_id'] = null;
            $this->incrementMetric('martial_classes');
        }

        // Track metric
        $this->incrementMetric('base_classes_processed');

        return $data;
    }
}
```

**Step 4: Run tests to verify they pass**

```bash
php artisan test --filter=BaseClassStrategyTest
```

Expected: PASS - 8 tests

**Step 5: Run full test suite**

```bash
php artisan test
```

Expected: 1,182 tests passing (+8)

**Step 6: Format and commit**

```bash
./vendor/bin/pint
git add app/Services/Importers/Strategies/CharacterClass/BaseClassStrategy.php tests/Unit/Strategies/CharacterClass/BaseClassStrategyTest.php
git commit -m "feat: add BaseClassStrategy for base classes

Handles base classes with spellcasting detection and validation.

🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

## Task 8: SubclassStrategy

**Files:**
- Create: `app/Services/Importers/Strategies/CharacterClass/SubclassStrategy.php`
- Create: `tests/Unit/Strategies/CharacterClass/SubclassStrategyTest.php`

**Step 1: Write failing tests**

```php
<?php

namespace Tests\Unit\Strategies\CharacterClass;

use App\Models\CharacterClass;
use App\Services\Importers\Strategies\CharacterClass\SubclassStrategy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SubclassStrategyTest extends TestCase
{
    use RefreshDatabase;

    private SubclassStrategy $strategy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->strategy = new SubclassStrategy();
    }

    #[Test]
    public function it_applies_to_subclasses(): void
    {
        $data = ['name' => 'School of Evocation', 'hit_die' => 0];

        $this->assertTrue($this->strategy->appliesTo($data));
    }

    #[Test]
    public function it_does_not_apply_to_base_classes(): void
    {
        $data = ['name' => 'Wizard', 'hit_die' => 6];

        $this->assertFalse($this->strategy->appliesTo($data));
    }

    #[Test]
    public function it_resolves_parent_from_school_pattern(): void
    {
        $parent = CharacterClass::factory()->create(['name' => 'Wizard', 'slug' => 'wizard']);

        $data = ['name' => 'School of Evocation', 'hit_die' => 0];

        $result = $this->strategy->enhance($data);

        $this->assertEquals($parent->id, $result['parent_class_id']);
    }

    #[Test]
    public function it_resolves_parent_from_oath_pattern(): void
    {
        $parent = CharacterClass::factory()->create(['name' => 'Paladin', 'slug' => 'paladin']);

        $data = ['name' => 'Oath of Vengeance', 'hit_die' => 0];

        $result = $this->strategy->enhance($data);

        $this->assertEquals($parent->id, $result['parent_class_id']);
    }

    #[Test]
    public function it_resolves_parent_from_circle_pattern(): void
    {
        $parent = CharacterClass::factory()->create(['name' => 'Druid', 'slug' => 'druid']);

        $data = ['name' => 'Circle of the Moon', 'hit_die' => 0];

        $result = $this->strategy->enhance($data);

        $this->assertEquals($parent->id, $result['parent_class_id']);
    }

    #[Test]
    public function it_inherits_hit_die_from_parent(): void
    {
        CharacterClass::factory()->create(['name' => 'Wizard', 'slug' => 'wizard', 'hit_die' => 6]);

        $data = ['name' => 'School of Evocation', 'hit_die' => 0];

        $result = $this->strategy->enhance($data);

        $this->assertEquals(6, $result['hit_die']);
    }

    #[Test]
    public function it_generates_subclass_slug(): void
    {
        CharacterClass::factory()->create(['name' => 'Wizard', 'slug' => 'wizard']);

        $data = ['name' => 'School of Evocation', 'hit_die' => 0];

        $result = $this->strategy->enhance($data);

        $this->assertEquals('wizard-school-of-evocation', $result['slug']);
    }

    #[Test]
    public function it_warns_if_parent_not_found(): void
    {
        $data = ['name' => 'School of Evocation', 'hit_die' => 0];

        $this->strategy->enhance($data);

        $warnings = $this->strategy->getWarnings();
        $this->assertCount(1, $warnings);
        $this->assertStringContainsString('Parent class', $warnings[0]);
    }

    #[Test]
    public function it_tracks_subclasses_processed_metric(): void
    {
        CharacterClass::factory()->create(['name' => 'Wizard', 'slug' => 'wizard']);

        $data = ['name' => 'School of Evocation', 'hit_die' => 0];

        $this->strategy->enhance($data);

        $metrics = $this->strategy->getMetrics();
        $this->assertEquals(1, $metrics['subclasses_processed']);
    }

    #[Test]
    public function it_tracks_parent_classes_resolved_metric(): void
    {
        CharacterClass::factory()->create(['name' => 'Wizard', 'slug' => 'wizard']);

        $data = ['name' => 'School of Evocation', 'hit_die' => 0];

        $this->strategy->enhance($data);

        $metrics = $this->strategy->getMetrics();
        $this->assertEquals(1, $metrics['parent_classes_resolved']);
    }
}
```

**Step 2: Run tests to verify they fail**

```bash
php artisan test --filter=SubclassStrategyTest
```

Expected: FAIL - "Class not found"

**Step 3: Implement SubclassStrategy**

```php
<?php

namespace App\Services\Importers\Strategies\CharacterClass;

use App\Models\CharacterClass;
use Illuminate\Support\Str;

class SubclassStrategy extends AbstractClassStrategy
{
    /**
     * Subclasses have hit_die = 0 (supplemental data only).
     */
    public function appliesTo(array $data): bool
    {
        return ($data['hit_die'] ?? 0) === 0;
    }

    /**
     * Enhance subclass data with parent resolution and hit_die inheritance.
     */
    public function enhance(array $data): array
    {
        $parentName = $this->detectParentClassName($data['name']);
        $parent = CharacterClass::where('slug', Str::slug($parentName))->first();

        if ($parent) {
            $data['parent_class_id'] = $parent->id;
            $data['hit_die'] = $parent->hit_die;
            $data['slug'] = Str::slug($parentName).'-'.Str::slug($data['name']);

            $this->incrementMetric('parent_classes_resolved');
        } else {
            $this->addWarning("Parent class '{$parentName}' not found for subclass '{$data['name']}'");
            $data['parent_class_id'] = null;
            $data['slug'] = Str::slug($data['name']);
        }

        $this->incrementMetric('subclasses_processed');

        return $data;
    }

    /**
     * Detect parent class name from subclass name patterns.
     */
    private function detectParentClassName(string $subclassName): string
    {
        if (str_contains($subclassName, 'School of')) {
            return 'Wizard';
        }

        if (str_contains($subclassName, 'Oath of')) {
            return 'Paladin';
        }

        if (str_contains($subclassName, 'Circle of')) {
            return 'Druid';
        }

        if (str_contains($subclassName, 'Path of')) {
            return 'Barbarian';
        }

        if (str_contains($subclassName, 'College of')) {
            return 'Bard';
        }

        if (str_contains($subclassName, 'Domain')) {
            return 'Cleric';
        }

        if (str_contains($subclassName, 'Archetype')) {
            return 'Fighter';
        }

        if (str_contains($subclassName, 'Tradition')) {
            return 'Monk';
        }

        if (str_contains($subclassName, 'Conclave')) {
            return 'Ranger';
        }

        if (str_contains($subclassName, 'Patron')) {
            return 'Warlock';
        }

        if (str_contains($subclassName, 'Way of')) {
            return 'Rogue';
        }

        // Default: try to extract first word
        $words = explode(' ', $subclassName);

        return $words[0] ?? 'Unknown';
    }
}
```

**Step 4: Run tests to verify they pass**

```bash
php artisan test --filter=SubclassStrategyTest
```

Expected: PASS - 10 tests

**Step 5: Run full test suite**

```bash
php artisan test
```

Expected: 1,192 tests passing (+10)

**Step 6: Format and commit**

```bash
./vendor/bin/pint
git add app/Services/Importers/Strategies/CharacterClass/SubclassStrategy.php tests/Unit/Strategies/CharacterClass/SubclassStrategyTest.php
git commit -m "feat: add SubclassStrategy for subclasses

Handles subclasses with parent resolution via name patterns and
hit_die inheritance.

🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

## Task 9: Refactor ClassImporter to Use Strategies

**Files:**
- Modify: `app/Services/Importers/ClassImporter.php`

**Step 1: Read current ClassImporter**

```bash
cat app/Services/Importers/ClassImporter.php
```

**Step 2: Refactor ClassImporter**

Replace the importEntity method and add strategy initialization:

```php
<?php

namespace App\Services\Importers;

use App\Models\AbilityScore;
use App\Models\CharacterClass;
use App\Models\ClassCounter;
use App\Models\ClassFeature;
use App\Models\ClassLevelProgression;
use App\Services\Importers\Strategies\CharacterClass\BaseClassStrategy;
use App\Services\Importers\Strategies\CharacterClass\SubclassStrategy;
use App\Services\Parsers\ClassXmlParser;
use Illuminate\Support\Facades\Log;

class ClassImporter extends BaseImporter
{
    private array $strategies = [];

    public function __construct(ClassXmlParser $parser)
    {
        parent::__construct($parser);
        $this->initializeStrategies();
    }

    /**
     * Initialize class import strategies.
     */
    private function initializeStrategies(): void
    {
        $this->strategies = [
            new BaseClassStrategy(),
            new SubclassStrategy(),
        ];
    }

    /**
     * Import a class from parsed data.
     */
    protected function importEntity(array $data): CharacterClass
    {
        // Apply all applicable strategies
        foreach ($this->strategies as $strategy) {
            if ($strategy->appliesTo($data)) {
                $data = $strategy->enhance($data);
                $this->logStrategyApplication($strategy, $data);
            }
        }

        // If slug not set by strategy, generate from name
        if (! isset($data['slug'])) {
            $data['slug'] = $this->generateSlug($data['name']);
        }

        // Build description from traits if not directly provided
        $description = $data['description'] ?? null;
        if (empty($description) && ! empty($data['traits'])) {
            $description = $data['traits'][0]['description'] ?? '';
        }

        // Create or update class using slug as unique key
        $class = CharacterClass::updateOrCreate(
            ['slug' => $data['slug']],
            [
                'name' => $data['name'],
                'parent_class_id' => $data['parent_class_id'] ?? null,
                'hit_die' => $data['hit_die'],
                'description' => $description ?: 'No description available',
                'spellcasting_ability_id' => $data['spellcasting_ability_id'] ?? null,
            ]
        );

        // Import relationships
        $this->importSources($class, $data['sources'] ?? []);
        $this->importTraits($class, $data['traits'] ?? []);
        $this->importProficiencies($class, $data['proficiencies'] ?? []);

        // Import level progression if present
        if (! empty($data['level_progression'])) {
            $this->importLevelProgression($class, $data['level_progression']);
        }

        // Import features if present
        if (! empty($data['features'])) {
            $this->importFeatures($class, $data['features']);
        }

        // Import counters if present
        if (! empty($data['counters'])) {
            $this->importCounters($class, $data['counters']);
        }

        return $class;
    }

    /**
     * Log strategy application to import-strategy channel.
     */
    private function logStrategyApplication($strategy, array $data): void
    {
        Log::channel('import-strategy')->info('Strategy applied', [
            'class' => $data['name'],
            'strategy' => class_basename($strategy),
            'warnings' => $strategy->getWarnings(),
            'metrics' => $strategy->getMetrics(),
        ]);

        // Reset strategy for next entity
        $strategy->reset();
    }

    // Keep existing private methods: importLevelProgression, importFeatures, importCounters
}
```

**Step 3: Run existing tests**

```bash
php artisan test --filter=ClassImporterTest
```

Expected: All existing tests pass

**Step 4: Run full test suite**

```bash
php artisan test
```

Expected: 1,192 tests passing (no change - refactor only)

**Step 5: Test actual import**

```bash
php artisan migrate:fresh --seed
php artisan import:classes import-files/class-phb.xml
```

Expected: Successfully imports classes with strategy logging

**Step 6: Format and commit**

```bash
./vendor/bin/pint
git add app/Services/Importers/ClassImporter.php
git commit -m "refactor: integrate strategy pattern into ClassImporter

Reduces ClassImporter from 263 to ~120 lines (-54%) by extracting
type-specific logic into composable strategies.

- BaseClassStrategy handles base classes with spellcasting detection
- SubclassStrategy handles subclasses with parent resolution

All existing tests pass, no functional changes.

🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

## Task 10: Update Documentation

**Files:**
- Modify: `CLAUDE.md`
- Modify: `CHANGELOG.md`
- Create: `docs/SESSION-HANDOVER-2025-11-22-IMPORTER-STRATEGY-REFACTOR-PHASE1.md`

**Step 1: Update CLAUDE.md**

Add to the "Strategy Pattern" section:

```markdown
### Strategy Pattern (6 of 9 Importers)

**Architecture:** Type-specific parsing strategies for extensibility and code reduction

**Importers Using Strategies:**
- ✅ ItemImporter (5 strategies: Charged, Scroll, Potion, Tattoo, Legendary)
- ✅ MonsterImporter (5 strategies: Default, Dragon, Spellcaster, Undead, Swarm)
- ✅ RaceImporter (3 strategies: BaseRace, Subrace, RacialVariant) - Phase 1
- ✅ ClassImporter (2 strategies: BaseClass, Subclass) - Phase 1

**Code Reduction (Phase 1):**
- RaceImporter: 347 → 180 lines (-48%)
- ClassImporter: 263 → 120 lines (-54%)
- Total: 610 → 300 lines (-51%)

**Benefits:**
- Uniform architecture across importers
- Type-specific logic isolated and testable
- Easy to add new strategies without modifying core importer
- Consistent logging and statistics
```

**Step 2: Update CHANGELOG.md**

```markdown
### [Unreleased]

#### Changed - Phase 1 Importer Strategy Refactoring (2025-11-22)
- **RaceImporter:** Refactored to use Strategy Pattern (3 strategies)
  - BaseRaceStrategy: Handles base races (Elf, Dwarf, Human)
  - SubraceStrategy: Handles subraces with parent resolution (High Elf, Mountain Dwarf)
  - RacialVariantStrategy: Handles variants (Dragonborn colors, Tiefling bloodlines)
  - Code reduction: 347 → 180 lines (-48%)
- **ClassImporter:** Refactored to use Strategy Pattern (2 strategies)
  - BaseClassStrategy: Handles base classes with spellcasting detection (Wizard, Fighter)
  - SubclassStrategy: Handles subclasses with parent resolution (School of Evocation)
  - Code reduction: 263 → 120 lines (-54%)

#### Added
- 5 new strategy classes (3 race, 2 class)
- 58 new strategy unit tests with real XML fixtures
- Strategy statistics logging and display for race/class imports
- AbstractRaceStrategy and AbstractClassStrategy base classes
```

**Step 3: Create session handover document**

Create comprehensive handover (use design doc as template, update with actual results).

**Step 4: Commit documentation**

```bash
git add CLAUDE.md CHANGELOG.md docs/SESSION-HANDOVER-2025-11-22-IMPORTER-STRATEGY-REFACTOR-PHASE1.md
git commit -m "docs: complete Phase 1 strategy refactoring documentation

Updated project documentation with Phase 1 results and metrics.

🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

## Verification Checklist

**Before considering Phase 1 complete:**

```bash
# 1. All tests passing
php artisan test
# Expected: 1,199 tests passing (1,141 + 58 new)

# 2. Code formatted
./vendor/bin/pint
# Expected: All files formatted

# 3. Race import works
php artisan migrate:fresh --seed
php artisan import:races import-files/races-phb.xml
# Expected: ~115 races imported with strategy statistics

# 4. Class import works
php artisan import:classes import-files/class-phb.xml
# Expected: Classes imported with strategy statistics

# 5. Strategy logs generated
ls storage/logs/import-strategy-*.log
# Expected: Log file exists with JSON entries

# 6. Git status clean
git status
# Expected: Nothing to commit, working tree clean

# 7. Line count verification
wc -l app/Services/Importers/RaceImporter.php
# Expected: ~180 lines

wc -l app/Services/Importers/ClassImporter.php
# Expected: ~120 lines
```

---

## Success Criteria

✅ **Code Quality:**
- RaceImporter: 347 → ~180 lines (-48%)
- ClassImporter: 263 → ~120 lines (-54%)
- All 1,199 tests passing
- Laravel Pint formatted
- No PHPStan errors

✅ **Architecture:**
- Consistent with Item/Monster patterns
- Each strategy <100 lines
- Clear separation of concerns
- Independently testable

✅ **Functional:**
- All existing imports work
- Strategy statistics display correctly
- Structured logging operational

---

## Estimated Duration

- **Tasks 1-5 (RaceImporter):** 4-6 hours
- **Tasks 6-9 (ClassImporter):** 3-4 hours
- **Task 10 (Documentation):** 1-2 hours
- **Total:** 8-12 hours

---

**Plan Complete:** Ready for execution via superpowers:executing-plans or superpowers:subagent-driven-development
