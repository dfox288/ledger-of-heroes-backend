# Monster Importer Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Import D&D 5e monsters from 9 bestiary XML files using Strategy Pattern for type-specific parsing (dragons, spellcasters, undead, swarms).

**Architecture:** MonsterXmlParser parses XML to arrays → MonsterImporter selects strategy based on type → Strategy enhances traits/actions/legendary → Import to 5 related tables (traits, actions, legendary_actions, spellcasting, monster_spells).

**Tech Stack:** Laravel 12, PHP 8.4, PHPUnit 11+, SimpleXML, Strategy Pattern

**Design Doc:** `docs/plans/2025-11-22-monster-importer-strategy-pattern.md`

---

## Task 1: Create Monster Model Relationships

**Files:**
- Modify: `app/Models/Monster.php` (create file)
- Test: `tests/Unit/Models/MonsterTest.php` (create file)

**Step 1: Write the failing test**

Create `tests/Unit/Models/MonsterTest.php`:

```php
<?php

namespace Tests\Unit\Models;

use App\Models\Monster;
use App\Models\MonsterTrait;
use App\Models\MonsterAction;
use App\Models\MonsterLegendaryAction;
use App\Models\MonsterSpellcasting;
use App\Models\Size;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonsterTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_belongs_to_size(): void
    {
        $monster = Monster::factory()->create();

        $this->assertInstanceOf(Size::class, $monster->size);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_has_many_traits(): void
    {
        $monster = Monster::factory()->create();
        MonsterTrait::factory()->count(3)->create(['monster_id' => $monster->id]);

        $this->assertCount(3, $monster->traits);
        $this->assertInstanceOf(MonsterTrait::class, $monster->traits->first());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_has_many_actions(): void
    {
        $monster = Monster::factory()->create();
        MonsterAction::factory()->count(2)->create(['monster_id' => $monster->id]);

        $this->assertCount(2, $monster->actions);
        $this->assertInstanceOf(MonsterAction::class, $monster->actions->first());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_has_many_legendary_actions(): void
    {
        $monster = Monster::factory()->create();
        MonsterLegendaryAction::factory()->count(3)->create(['monster_id' => $monster->id]);

        $this->assertCount(3, $monster->legendaryActions);
        $this->assertInstanceOf(MonsterLegendaryAction::class, $monster->legendaryActions->first());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_has_one_spellcasting(): void
    {
        $monster = Monster::factory()->create();
        MonsterSpellcasting::factory()->create(['monster_id' => $monster->id]);

        $this->assertInstanceOf(MonsterSpellcasting::class, $monster->spellcasting);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_does_not_use_timestamps(): void
    {
        $this->assertFalse(Monster::make()->usesTimestamps());
    }
}
```

**Step 2: Run test to verify it fails**

```bash
docker compose exec php php artisan test --filter=MonsterTest
```

Expected: FAIL with "Class 'App\Models\Monster' not found"

**Step 3: Create Monster model with relationships**

Create `app/Models/Monster.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class Monster extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'name',
        'slug',
        'size_id',
        'type',
        'alignment',
        'armor_class',
        'armor_type',
        'hit_points_average',
        'hit_dice',
        'speed_walk',
        'speed_fly',
        'speed_swim',
        'speed_burrow',
        'speed_climb',
        'can_hover',
        'strength',
        'dexterity',
        'constitution',
        'intelligence',
        'wisdom',
        'charisma',
        'challenge_rating',
        'experience_points',
        'description',
    ];

    protected $casts = [
        'can_hover' => 'boolean',
    ];

    public function size(): BelongsTo
    {
        return $this->belongsTo(Size::class);
    }

    public function traits(): HasMany
    {
        return $this->hasMany(MonsterTrait::class);
    }

    public function actions(): HasMany
    {
        return $this->hasMany(MonsterAction::class);
    }

    public function legendaryActions(): HasMany
    {
        return $this->hasMany(MonsterLegendaryAction::class);
    }

    public function spellcasting(): HasOne
    {
        return $this->hasOne(MonsterSpellcasting::class);
    }

    public function spells(): MorphToMany
    {
        return $this->morphToMany(
            Spell::class,
            'entity',
            'monster_spells',
            'monster_id',
            'spell_id'
        )->withPivot('usage_type', 'usage_limit');
    }

    public function modifiers(): MorphMany
    {
        return $this->morphMany(Modifier::class, 'entity');
    }

    public function conditions(): MorphToMany
    {
        return $this->morphToMany(Condition::class, 'entity', 'entity_conditions')
            ->withPivot('description');
    }

    public function sources(): MorphToMany
    {
        return $this->morphToMany(Source::class, 'entity', 'entity_sources')
            ->withPivot('source_pages');
    }
}
```

**Step 4: Create related models (MonsterTrait, MonsterAction, etc.)**

Create `app/Models/MonsterTrait.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonsterTrait extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'monster_id',
        'name',
        'description',
        'attack_data',
        'sort_order',
    ];

    public function monster(): BelongsTo
    {
        return $this->belongsTo(Monster::class);
    }
}
```

Create `app/Models/MonsterAction.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonsterAction extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'monster_id',
        'action_type',
        'name',
        'description',
        'attack_data',
        'recharge',
        'sort_order',
    ];

    public function monster(): BelongsTo
    {
        return $this->belongsTo(Monster::class);
    }
}
```

Create `app/Models/MonsterLegendaryAction.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonsterLegendaryAction extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'monster_id',
        'name',
        'description',
        'action_cost',
        'is_lair_action',
        'attack_data',
        'recharge',
        'sort_order',
    ];

    protected $casts = [
        'is_lair_action' => 'boolean',
    ];

    public function monster(): BelongsTo
    {
        return $this->belongsTo(Monster::class);
    }
}
```

Create `app/Models/MonsterSpellcasting.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonsterSpellcasting extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'monster_id',
        'description',
        'spell_slots',
        'spellcasting_ability',
        'spell_save_dc',
        'spell_attack_bonus',
    ];

    public function monster(): BelongsTo
    {
        return $this->belongsTo(Monster::class);
    }
}
```

**Step 5: Run test to verify it passes**

```bash
docker compose exec php php artisan test --filter=MonsterTest
```

Expected: FAIL with "MonsterFactory not found" (we need factories)

**Step 6: Create Monster factory**

Create `database/factories/MonsterFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Monster;
use App\Models\Size;
use Illuminate\Database\Eloquent\Factories\Factory;

class MonsterFactory extends Factory
{
    protected $model = Monster::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'slug' => fake()->slug(),
            'size_id' => Size::factory(),
            'type' => fake()->randomElement(['beast', 'humanoid', 'dragon', 'undead', 'aberration']),
            'alignment' => fake()->randomElement(['Lawful Good', 'Neutral Evil', 'Chaotic Neutral', null]),
            'armor_class' => fake()->numberBetween(10, 22),
            'armor_type' => fake()->optional()->randomElement(['natural armor', 'plate mail']),
            'hit_points_average' => fake()->numberBetween(10, 500),
            'hit_dice' => fake()->numberBetween(1, 30) . 'd' . fake()->randomElement([6, 8, 10, 12]) . '+' . fake()->numberBetween(0, 50),
            'speed_walk' => fake()->numberBetween(0, 60),
            'speed_fly' => fake()->optional()->numberBetween(30, 120),
            'speed_swim' => fake()->optional()->numberBetween(20, 60),
            'speed_burrow' => fake()->optional()->numberBetween(10, 30),
            'speed_climb' => fake()->optional()->numberBetween(20, 40),
            'can_hover' => fake()->boolean(20),
            'strength' => fake()->numberBetween(1, 30),
            'dexterity' => fake()->numberBetween(1, 30),
            'constitution' => fake()->numberBetween(1, 30),
            'intelligence' => fake()->numberBetween(1, 30),
            'wisdom' => fake()->numberBetween(1, 30),
            'charisma' => fake()->numberBetween(1, 30),
            'challenge_rating' => fake()->randomElement(['0', '1/8', '1/4', '1/2', '1', '5', '10', '20']),
            'experience_points' => fake()->numberBetween(10, 50000),
            'description' => fake()->optional()->paragraph(),
        ];
    }
}
```

Create `database/factories/MonsterTraitFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Monster;
use App\Models\MonsterTrait;
use Illuminate\Database\Eloquent\Factories\Factory;

class MonsterTraitFactory extends Factory
{
    protected $model = MonsterTrait::class;

    public function definition(): array
    {
        return [
            'monster_id' => Monster::factory(),
            'name' => fake()->words(2, true),
            'description' => fake()->sentence(),
            'attack_data' => null,
            'sort_order' => 0,
        ];
    }
}
```

Create `database/factories/MonsterActionFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Monster;
use App\Models\MonsterAction;
use Illuminate\Database\Eloquent\Factories\Factory;

class MonsterActionFactory extends Factory
{
    protected $model = MonsterAction::class;

    public function definition(): array
    {
        return [
            'monster_id' => Monster::factory(),
            'action_type' => fake()->randomElement(['action', 'reaction', 'bonus_action']),
            'name' => fake()->words(2, true),
            'description' => fake()->sentence(),
            'attack_data' => null,
            'recharge' => null,
            'sort_order' => 0,
        ];
    }
}
```

Create `database/factories/MonsterLegendaryActionFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Monster;
use App\Models\MonsterLegendaryAction;
use Illuminate\Database\Eloquent\Factories\Factory;

class MonsterLegendaryActionFactory extends Factory
{
    protected $model = MonsterLegendaryAction::class;

    public function definition(): array
    {
        return [
            'monster_id' => Monster::factory(),
            'name' => fake()->words(2, true),
            'description' => fake()->sentence(),
            'action_cost' => fake()->numberBetween(1, 3),
            'is_lair_action' => false,
            'attack_data' => null,
            'recharge' => null,
            'sort_order' => 0,
        ];
    }
}
```

Create `database/factories/MonsterSpellcastingFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Monster;
use App\Models\MonsterSpellcasting;
use Illuminate\Database\Eloquent\Factories\Factory;

class MonsterSpellcastingFactory extends Factory
{
    protected $model = MonsterSpellcasting::class;

    public function definition(): array
    {
        return [
            'monster_id' => Monster::factory(),
            'description' => fake()->sentence(),
            'spell_slots' => '0,3,2,1',
            'spellcasting_ability' => fake()->randomElement(['Charisma', 'Intelligence', 'Wisdom']),
            'spell_save_dc' => fake()->numberBetween(10, 20),
            'spell_attack_bonus' => fake()->numberBetween(2, 12),
        ];
    }
}
```

**Step 7: Run test to verify it passes**

```bash
docker compose exec php php artisan test --filter=MonsterTest
```

Expected: PASS (6 tests)

**Step 8: Commit**

```bash
git add app/Models/Monster*.php database/factories/Monster*.php tests/Unit/Models/MonsterTest.php
git commit -m "feat: add Monster models with relationships and factories

- Monster model with 5 related tables (traits, actions, legendary, spellcasting)
- Polymorphic relationships: spells, modifiers, conditions, sources
- Factories for all monster-related models
- Unit tests verify relationships and timestamps

🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

## Task 2: Create AbstractMonsterStrategy Base Class

**Files:**
- Create: `app/Services/Importers/Strategies/Monster/AbstractMonsterStrategy.php`
- Test: `tests/Unit/Strategies/Monster/AbstractMonsterStrategyTest.php`

**Step 1: Write the failing test**

Create `tests/Unit/Strategies/Monster/AbstractMonsterStrategyTest.php`:

```php
<?php

namespace Tests\Unit\Strategies\Monster;

use Tests\TestCase;

class AbstractMonsterStrategyTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function it_extracts_action_cost_from_legendary_name(): void
    {
        $strategy = new class extends \App\Services\Importers\Strategies\Monster\AbstractMonsterStrategy {
            public function appliesTo(array $monsterData): bool { return true; }

            public function testExtractCost(string $name): int
            {
                return $this->extractActionCost($name);
            }
        };

        $this->assertEquals(1, $strategy->testExtractCost('Detect'));
        $this->assertEquals(2, $strategy->testExtractCost('Wing Attack (Costs 2 Actions)'));
        $this->assertEquals(3, $strategy->testExtractCost('Psychic Drain (Costs 3 Actions)'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_detects_lair_actions_from_category(): void
    {
        $strategy = new class extends \App\Services\Importers\Strategies\Monster\AbstractMonsterStrategy {
            public function appliesTo(array $monsterData): bool { return true; }
        };

        $legendary = [
            ['name' => 'Detect', 'description' => '...', 'category' => null],
            ['name' => 'Lair Actions', 'description' => '...', 'category' => 'lair'],
        ];

        $enhanced = $strategy->enhanceLegendaryActions($legendary, []);

        $this->assertEquals(1, $enhanced[0]['action_cost']);
        $this->assertFalse($enhanced[0]['is_lair_action']);

        $this->assertEquals(1, $enhanced[1]['action_cost']);
        $this->assertTrue($enhanced[1]['is_lair_action']);
    }
}
```

**Step 2: Run test to verify it fails**

```bash
docker compose exec php php artisan test --filter=AbstractMonsterStrategyTest
```

Expected: FAIL with "Class 'App\Services\Importers\Strategies\Monster\AbstractMonsterStrategy' not found"

**Step 3: Create AbstractMonsterStrategy**

Create `app/Services/Importers/Strategies/Monster/AbstractMonsterStrategy.php`:

```php
<?php

namespace App\Services\Importers\Strategies\Monster;

use App\Models\Monster;

abstract class AbstractMonsterStrategy
{
    /**
     * Determine if this strategy applies to the given monster data
     */
    abstract public function appliesTo(array $monsterData): bool;

    /**
     * Apply type-specific enhancements to parsed traits
     */
    public function enhanceTraits(array $traits, array $monsterData): array
    {
        return $traits; // Default: no enhancement
    }

    /**
     * Apply type-specific action parsing (multiattack, recharge, etc.)
     */
    public function enhanceActions(array $actions, array $monsterData): array
    {
        return $actions; // Default: no enhancement
    }

    /**
     * Parse legendary actions with cost detection
     */
    public function enhanceLegendaryActions(array $legendary, array $monsterData): array
    {
        foreach ($legendary as &$action) {
            // Extract cost from name: "Psychic Drain (Costs 2 Actions)" → 2
            $action['action_cost'] = $this->extractActionCost($action['name']);

            // Detect lair actions via category attribute
            $action['is_lair_action'] = ($action['category'] ?? null) === 'lair';
        }

        return $legendary;
    }

    /**
     * Post-creation hook for additional relationship syncing
     * (e.g., SpellcasterStrategy syncs spells)
     */
    public function afterCreate(Monster $monster, array $monsterData): void
    {
        // Override in strategies that need post-creation work
    }

    /**
     * Extract metadata for logging and statistics
     */
    public function extractMetadata(array $monsterData): array
    {
        return []; // Strategy-specific metrics
    }

    /**
     * Extract action cost from legendary action name
     * "Wing Attack (Costs 2 Actions)" → 2
     * "Detect" → 1 (default)
     */
    protected function extractActionCost(string $name): int
    {
        if (preg_match('/\(Costs? (\d+) Actions?\)/i', $name, $matches)) {
            return (int) $matches[1];
        }

        return 1; // Default cost
    }
}
```

**Step 4: Run test to verify it passes**

```bash
docker compose exec php php artisan test --filter=AbstractMonsterStrategyTest
```

Expected: PASS (2 tests)

**Step 5: Commit**

```bash
git add app/Services/Importers/Strategies/Monster/AbstractMonsterStrategy.php tests/Unit/Strategies/Monster/AbstractMonsterStrategyTest.php
git commit -m "feat: add AbstractMonsterStrategy base class

- Strategy interface with appliesTo() method
- Default implementations for enhanceTraits/Actions/Legendary
- Action cost extraction from legendary names (Costs 2 Actions → 2)
- Lair action detection via category attribute
- afterCreate() hook for spell syncing
- Unit tests verify cost extraction and lair detection

🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

## Task 3: Implement Default Strategy

**Files:**
- Create: `app/Services/Importers/Strategies/Monster/DefaultStrategy.php`
- Test: `tests/Unit/Strategies/Monster/DefaultStrategyTest.php`

**Step 1: Write the failing test**

Create `tests/Unit/Strategies/Monster/DefaultStrategyTest.php`:

```php
<?php

namespace Tests\Unit\Strategies\Monster;

use App\Services\Importers\Strategies\Monster\DefaultStrategy;
use Tests\TestCase;

class DefaultStrategyTest extends TestCase
{
    protected DefaultStrategy $strategy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->strategy = new DefaultStrategy();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_always_applies(): void
    {
        $this->assertTrue($this->strategy->appliesTo(['type' => 'beast']));
        $this->assertTrue($this->strategy->appliesTo(['type' => 'monstrosity']));
        $this->assertTrue($this->strategy->appliesTo(['type' => 'anything']));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_traits_unmodified(): void
    {
        $traits = [
            ['name' => 'Keen Smell', 'description' => 'Advantage on Wisdom checks...'],
        ];

        $enhanced = $this->strategy->enhanceTraits($traits, []);

        $this->assertEquals($traits, $enhanced);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_actions_unmodified(): void
    {
        $actions = [
            ['name' => 'Bite', 'description' => 'Melee Weapon Attack...'],
        ];

        $enhanced = $this->strategy->enhanceActions($actions, []);

        $this->assertEquals($actions, $enhanced);
    }
}
```

**Step 2: Run test to verify it fails**

```bash
docker compose exec php php artisan test --filter=DefaultStrategyTest
```

Expected: FAIL with "Class 'App\Services\Importers\Strategies\Monster\DefaultStrategy' not found"

**Step 3: Create DefaultStrategy**

Create `app/Services/Importers/Strategies/Monster/DefaultStrategy.php`:

```php
<?php

namespace App\Services\Importers\Strategies\Monster;

class DefaultStrategy extends AbstractMonsterStrategy
{
    public function appliesTo(array $monsterData): bool
    {
        return true; // Always applicable as fallback
    }

    // Uses base implementations (no enhancements)
}
```

**Step 4: Run test to verify it passes**

```bash
docker compose exec php php artisan test --filter=DefaultStrategyTest
```

Expected: PASS (3 tests)

**Step 5: Commit**

```bash
git add app/Services/Importers/Strategies/Monster/DefaultStrategy.php tests/Unit/Strategies/Monster/DefaultStrategyTest.php
git commit -m "feat: add DefaultStrategy for monsters

- Fallback strategy for all monster types (beasts, monstrosities, etc.)
- Always applies (returns true)
- No type-specific enhancements (uses base class defaults)
- Unit tests verify behavior

🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

## Task 4: Implement DragonStrategy

**Files:**
- Create: `app/Services/Importers/Strategies/Monster/DragonStrategy.php`
- Test: `tests/Unit/Strategies/Monster/DragonStrategyTest.php`

**Step 1: Write the failing test**

Create `tests/Unit/Strategies/Monster/DragonStrategyTest.php`:

```php
<?php

namespace Tests\Unit\Strategies\Monster;

use App\Services\Importers\Strategies\Monster\DragonStrategy;
use Tests\TestCase;

class DragonStrategyTest extends TestCase
{
    protected DragonStrategy $strategy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->strategy = new DragonStrategy();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_applies_to_dragon_type(): void
    {
        $this->assertTrue($this->strategy->appliesTo(['type' => 'dragon']));
        $this->assertTrue($this->strategy->appliesTo(['type' => 'Dragon']));
        $this->assertFalse($this->strategy->appliesTo(['type' => 'humanoid']));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_extracts_breath_weapon_recharge(): void
    {
        $actions = [
            [
                'name' => 'Fire Breath (Recharge 5-6)',
                'description' => 'The dragon exhales fire...',
                'recharge' => null,
            ],
        ];

        $enhanced = $this->strategy->enhanceActions($actions, []);

        $this->assertEquals('5-6', $enhanced[0]['recharge']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_extracts_legendary_resistance_recharge(): void
    {
        $traits = [
            [
                'name' => 'Legendary Resistance (3/Day)',
                'description' => 'If the dragon fails a saving throw...',
                'recharge' => null,
            ],
        ];

        $enhanced = $this->strategy->enhanceTraits($traits, []);

        $this->assertEquals('3/DAY', $enhanced[0]['recharge']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_extracts_metadata(): void
    {
        $monsterData = [
            'traits' => [
                ['name' => 'Legendary Resistance (3/Day)', 'description' => '...'],
            ],
            'actions' => [
                ['name' => 'Fire Breath (Recharge 5-6)', 'description' => '...'],
                ['name' => 'Bite', 'description' => '...'],
            ],
            'legendary' => [
                ['name' => 'Detect', 'description' => '...', 'category' => null],
                ['name' => 'Lair Actions', 'description' => '...', 'category' => 'lair'],
            ],
        ];

        $metadata = $this->strategy->extractMetadata($monsterData);

        $this->assertEquals(1, $metadata['breath_weapons_detected']);
        $this->assertTrue($metadata['legendary_resistance']);
        $this->assertEquals(1, $metadata['lair_actions']);
    }
}
```

**Step 2: Run test to verify it fails**

```bash
docker compose exec php php artisan test --filter=DragonStrategyTest
```

Expected: FAIL with "Class 'App\Services\Importers\Strategies\Monster\DragonStrategy' not found"

**Step 3: Create DragonStrategy**

Create `app/Services/Importers/Strategies/Monster/DragonStrategy.php`:

```php
<?php

namespace App\Services\Importers\Strategies\Monster;

class DragonStrategy extends AbstractMonsterStrategy
{
    public function appliesTo(array $monsterData): bool
    {
        return str_contains(strtolower($monsterData['type']), 'dragon');
    }

    public function enhanceActions(array $actions, array $monsterData): array
    {
        foreach ($actions as &$action) {
            // Detect breath weapon pattern and extract recharge
            if (str_contains($action['name'], 'Breath')) {
                // Extract recharge: "Fire Breath (Recharge 5-6)" → "5-6"
                if (preg_match('/\(Recharge ([\d\-]+)\)/i', $action['name'], $matches)) {
                    $action['recharge'] = $matches[1];
                }
            }

            // Future: Could extract multiattack patterns here
            // if ($action['name'] === 'Multiattack') { ... }
        }

        return $actions;
    }

    public function enhanceTraits(array $traits, array $monsterData): array
    {
        foreach ($traits as &$trait) {
            // Legendary Resistance (3/Day) → extract recharge
            if (str_contains($trait['name'], 'Legendary Resistance')) {
                if (preg_match('/\((\d+)\/Day\)/i', $trait['name'], $matches)) {
                    $trait['recharge'] = $matches[1] . '/DAY';
                }
            }
        }

        return $traits;
    }

    public function extractMetadata(array $monsterData): array
    {
        $breathWeapons = collect($monsterData['actions'] ?? [])
            ->filter(fn ($a) => str_contains($a['name'], 'Breath'))
            ->count();

        $lairActions = collect($monsterData['legendary'] ?? [])
            ->filter(fn ($l) => ($l['category'] ?? null) === 'lair')
            ->count();

        return [
            'breath_weapons_detected' => $breathWeapons,
            'legendary_resistance' => collect($monsterData['traits'] ?? [])
                ->contains(fn ($t) => str_contains($t['name'], 'Legendary Resistance')),
            'lair_actions' => $lairActions,
        ];
    }
}
```

**Step 4: Run test to verify it passes**

```bash
docker compose exec php php artisan test --filter=DragonStrategyTest
```

Expected: PASS (4 tests)

**Step 5: Commit**

```bash
git add app/Services/Importers/Strategies/Monster/DragonStrategy.php tests/Unit/Strategies/Monster/DragonStrategyTest.php
git commit -m "feat: add DragonStrategy for dragon monsters

- Applies to type containing 'dragon'
- Extracts breath weapon recharge (Recharge 5-6)
- Extracts legendary resistance recharge (3/Day)
- Metadata: breath weapons, legendary resistance, lair actions
- Unit tests with 4 scenarios

🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

## Task 5: Implement UndeadStrategy

**Files:**
- Create: `app/Services/Importers/Strategies/Monster/UndeadStrategy.php`
- Test: `tests/Unit/Strategies/Monster/UndeadStrategyTest.php`

**Step 1: Write the failing test**

Create `tests/Unit/Strategies/Monster/UndeadStrategyTest.php`:

```php
<?php

namespace Tests\Unit\Strategies\Monster;

use App\Services\Importers\Strategies\Monster\UndeadStrategy;
use Tests\TestCase;

class UndeadStrategyTest extends TestCase
{
    protected UndeadStrategy $strategy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->strategy = new UndeadStrategy();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_applies_to_undead_type(): void
    {
        $this->assertTrue($this->strategy->appliesTo(['type' => 'undead']));
        $this->assertTrue($this->strategy->appliesTo(['type' => 'Undead']));
        $this->assertFalse($this->strategy->appliesTo(['type' => 'humanoid']));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_extracts_metadata_for_turn_resistance(): void
    {
        $monsterData = [
            'traits' => [
                ['name' => 'Turn Resistance', 'description' => 'The zombie has advantage on saving throws against any effect that turns undead.'],
            ],
        ];

        $metadata = $this->strategy->extractMetadata($monsterData);

        $this->assertTrue($metadata['has_turn_resistance']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_extracts_metadata_for_sunlight_sensitivity(): void
    {
        $monsterData = [
            'traits' => [
                ['name' => 'Sunlight Sensitivity', 'description' => 'While in sunlight...'],
            ],
        ];

        $metadata = $this->strategy->extractMetadata($monsterData);

        $this->assertTrue($metadata['has_sunlight_sensitivity']);
    }
}
```

**Step 2: Run test to verify it fails**

```bash
docker compose exec php php artisan test --filter=UndeadStrategyTest
```

Expected: FAIL with "Class 'App\Services\Importers\Strategies\Monster\UndeadStrategy' not found"

**Step 3: Create UndeadStrategy**

Create `app/Services/Importers/Strategies/Monster/UndeadStrategy.php`:

```php
<?php

namespace App\Services\Importers\Strategies\Monster;

class UndeadStrategy extends AbstractMonsterStrategy
{
    public function appliesTo(array $monsterData): bool
    {
        return strtolower($monsterData['type']) === 'undead';
    }

    public function extractMetadata(array $monsterData): array
    {
        return [
            'has_turn_resistance' => collect($monsterData['traits'] ?? [])
                ->contains(fn ($t) => str_contains(strtolower($t['description'] ?? ''), 'turn undead')),
            'has_sunlight_sensitivity' => collect($monsterData['traits'] ?? [])
                ->contains(fn ($t) => str_contains(strtolower($t['name'] ?? ''), 'sunlight')),
            'condition_immunities' => $monsterData['conditionImmune'] ?? '',
        ];
    }
}
```

**Step 4: Run test to verify it passes**

```bash
docker compose exec php php artisan test --filter=UndeadStrategyTest
```

Expected: PASS (3 tests)

**Step 5: Commit**

```bash
git add app/Services/Importers/Strategies/Monster/UndeadStrategy.php tests/Unit/Strategies/Monster/UndeadStrategyTest.php
git commit -m "feat: add UndeadStrategy for undead monsters

- Applies to type === 'undead'
- Metadata: turn resistance, sunlight sensitivity, condition immunities
- Unit tests verify detection logic

🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

## Task 6: Implement SwarmStrategy

**Files:**
- Create: `app/Services/Importers/Strategies/Monster/SwarmStrategy.php`
- Test: `tests/Unit/Strategies/Monster/SwarmStrategyTest.php`

**Step 1: Write the failing test**

Create `tests/Unit/Strategies/Monster/SwarmStrategyTest.php`:

```php
<?php

namespace Tests\Unit\Strategies\Monster;

use App\Services\Importers\Strategies\Monster\SwarmStrategy;
use Tests\TestCase;

class SwarmStrategyTest extends TestCase
{
    protected SwarmStrategy $strategy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->strategy = new SwarmStrategy();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_applies_to_swarm_type(): void
    {
        $this->assertTrue($this->strategy->appliesTo(['type' => 'swarm of medium beasts']));
        $this->assertTrue($this->strategy->appliesTo(['type' => 'Swarm of Tiny creatures']));
        $this->assertFalse($this->strategy->appliesTo(['type' => 'beast']));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_extracts_individual_creature_size_from_type(): void
    {
        $monsterData = [
            'type' => 'swarm of Medium beasts',
            'size' => 'M',
        ];

        $metadata = $this->strategy->extractMetadata($monsterData);

        $this->assertEquals('Medium', $metadata['individual_creature_size']);
        $this->assertEquals('M', $metadata['swarm_size']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_swarm_without_size_in_type(): void
    {
        $monsterData = [
            'type' => 'swarm',
            'size' => 'L',
        ];

        $metadata = $this->strategy->extractMetadata($monsterData);

        $this->assertNull($metadata['individual_creature_size']);
        $this->assertEquals('L', $metadata['swarm_size']);
    }
}
```

**Step 2: Run test to verify it fails**

```bash
docker compose exec php php artisan test --filter=SwarmStrategyTest
```

Expected: FAIL with "Class 'App\Services\Importers\Strategies\Monster\SwarmStrategy' not found"

**Step 3: Create SwarmStrategy**

Create `app/Services/Importers/Strategies/Monster/SwarmStrategy.php`:

```php
<?php

namespace App\Services\Importers\Strategies\Monster;

class SwarmStrategy extends AbstractMonsterStrategy
{
    public function appliesTo(array $monsterData): bool
    {
        return str_contains(strtolower($monsterData['type']), 'swarm');
    }

    public function extractMetadata(array $monsterData): array
    {
        // Extract individual creature size from type: "swarm of Medium beasts" → "Medium"
        $individualSize = null;
        if (preg_match('/swarm of (\w+)/i', $monsterData['type'], $matches)) {
            $individualSize = $matches[1];
        }

        return [
            'individual_creature_size' => $individualSize,
            'swarm_size' => $monsterData['size'],
        ];
    }
}
```

**Step 4: Run test to verify it passes**

```bash
docker compose exec php php artisan test --filter=SwarmStrategyTest
```

Expected: PASS (3 tests)

**Step 5: Commit**

```bash
git add app/Services/Importers/Strategies/Monster/SwarmStrategy.php tests/Unit/Strategies/Monster/SwarmStrategyTest.php
git commit -m "feat: add SwarmStrategy for swarm monsters

- Applies to type containing 'swarm'
- Extracts individual creature size from type string
- Metadata: individual_creature_size, swarm_size
- Unit tests verify size extraction

🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

**CONTINUE TO PART 2 (SpellcasterStrategy, Parser, Importer, Command, Tests)**

Due to length, this plan continues in a follow-up response. The remaining tasks are:

- Task 7: Implement SpellcasterStrategy (with ImportsEntitySpells trait)
- Task 8: Create MonsterXmlParser
- Task 9: Create MonsterImporter with strategy selection
- Task 10: Create import:monsters command
- Task 11: Update import:all command
- Task 12: Feature test for full import flow
- Task 13: Import all 9 bestiary files
- Task 14: Update documentation

**Total Estimated Time:** 6-8 hours with TDD
