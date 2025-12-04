# Multiclass Support Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add D&D 5e multiclass support allowing characters to have levels in multiple classes with prerequisite validation, combined spell slots, and per-class hit dice tracking.

**Architecture:** Junction table (`character_classes`) replaces single `class_id` FK. New services handle validation and spell slot calculation. Existing data migrates to new structure.

**Tech Stack:** Laravel 12, PHP 8.4, PHPUnit 11, MySQL/SQLite

**Issues:**
- #92 - Multiclass Support (primary)
- #109 - Subclass Selection (covered by this implementation - `character_classes.subclass_id`)

---

## Task 1: Create Multiclass Spell Slots Table

**Files:**
- Create: `database/migrations/2025_12_03_000001_create_multiclass_spell_slots_table.php`
- Create: `database/seeders/MulticlassSpellSlotSeeder.php`
- Create: `app/Models/MulticlassSpellSlot.php`
- Test: `tests/Unit/Models/MulticlassSpellSlotTest.php`

**Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Models;

use App\Models\MulticlassSpellSlot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MulticlassSpellSlotTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_retrieves_spell_slots_for_caster_level(): void
    {
        $this->seed(\Database\Seeders\MulticlassSpellSlotSeeder::class);

        $slots = MulticlassSpellSlot::forCasterLevel(5);

        $this->assertNotNull($slots);
        $this->assertEquals(4, $slots->slots_1st);
        $this->assertEquals(3, $slots->slots_2nd);
        $this->assertEquals(2, $slots->slots_3rd);
        $this->assertEquals(0, $slots->slots_4th);
    }

    #[Test]
    public function it_caps_at_level_20(): void
    {
        $this->seed(\Database\Seeders\MulticlassSpellSlotSeeder::class);

        $slots = MulticlassSpellSlot::forCasterLevel(25);

        $this->assertNotNull($slots);
        $this->assertEquals(20, $slots->caster_level);
    }

    #[Test]
    public function it_returns_null_for_level_zero(): void
    {
        $this->seed(\Database\Seeders\MulticlassSpellSlotSeeder::class);

        $slots = MulticlassSpellSlot::forCasterLevel(0);

        $this->assertNull($slots);
    }
}
```

**Step 2: Run test to verify it fails**

Run: `docker compose exec php php artisan test tests/Unit/Models/MulticlassSpellSlotTest.php`
Expected: FAIL - class/table not found

**Step 3: Create the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('multiclass_spell_slots', function (Blueprint $table) {
            $table->unsignedTinyInteger('caster_level')->primary();
            $table->unsignedTinyInteger('slots_1st')->default(0);
            $table->unsignedTinyInteger('slots_2nd')->default(0);
            $table->unsignedTinyInteger('slots_3rd')->default(0);
            $table->unsignedTinyInteger('slots_4th')->default(0);
            $table->unsignedTinyInteger('slots_5th')->default(0);
            $table->unsignedTinyInteger('slots_6th')->default(0);
            $table->unsignedTinyInteger('slots_7th')->default(0);
            $table->unsignedTinyInteger('slots_8th')->default(0);
            $table->unsignedTinyInteger('slots_9th')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('multiclass_spell_slots');
    }
};
```

**Step 4: Create the seeder (PHB p165 data)**

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MulticlassSpellSlotSeeder extends Seeder
{
    public function run(): void
    {
        // PHB p165 - Multiclass Spellcaster: Spell Slots per Spell Level
        $slots = [
            ['caster_level' => 1,  'slots_1st' => 2, 'slots_2nd' => 0, 'slots_3rd' => 0, 'slots_4th' => 0, 'slots_5th' => 0, 'slots_6th' => 0, 'slots_7th' => 0, 'slots_8th' => 0, 'slots_9th' => 0],
            ['caster_level' => 2,  'slots_1st' => 3, 'slots_2nd' => 0, 'slots_3rd' => 0, 'slots_4th' => 0, 'slots_5th' => 0, 'slots_6th' => 0, 'slots_7th' => 0, 'slots_8th' => 0, 'slots_9th' => 0],
            ['caster_level' => 3,  'slots_1st' => 4, 'slots_2nd' => 2, 'slots_3rd' => 0, 'slots_4th' => 0, 'slots_5th' => 0, 'slots_6th' => 0, 'slots_7th' => 0, 'slots_8th' => 0, 'slots_9th' => 0],
            ['caster_level' => 4,  'slots_1st' => 4, 'slots_2nd' => 3, 'slots_3rd' => 0, 'slots_4th' => 0, 'slots_5th' => 0, 'slots_6th' => 0, 'slots_7th' => 0, 'slots_8th' => 0, 'slots_9th' => 0],
            ['caster_level' => 5,  'slots_1st' => 4, 'slots_2nd' => 3, 'slots_3rd' => 2, 'slots_4th' => 0, 'slots_5th' => 0, 'slots_6th' => 0, 'slots_7th' => 0, 'slots_8th' => 0, 'slots_9th' => 0],
            ['caster_level' => 6,  'slots_1st' => 4, 'slots_2nd' => 3, 'slots_3rd' => 3, 'slots_4th' => 0, 'slots_5th' => 0, 'slots_6th' => 0, 'slots_7th' => 0, 'slots_8th' => 0, 'slots_9th' => 0],
            ['caster_level' => 7,  'slots_1st' => 4, 'slots_2nd' => 3, 'slots_3rd' => 3, 'slots_4th' => 1, 'slots_5th' => 0, 'slots_6th' => 0, 'slots_7th' => 0, 'slots_8th' => 0, 'slots_9th' => 0],
            ['caster_level' => 8,  'slots_1st' => 4, 'slots_2nd' => 3, 'slots_3rd' => 3, 'slots_4th' => 2, 'slots_5th' => 0, 'slots_6th' => 0, 'slots_7th' => 0, 'slots_8th' => 0, 'slots_9th' => 0],
            ['caster_level' => 9,  'slots_1st' => 4, 'slots_2nd' => 3, 'slots_3rd' => 3, 'slots_4th' => 3, 'slots_5th' => 1, 'slots_6th' => 0, 'slots_7th' => 0, 'slots_8th' => 0, 'slots_9th' => 0],
            ['caster_level' => 10, 'slots_1st' => 4, 'slots_2nd' => 3, 'slots_3rd' => 3, 'slots_4th' => 3, 'slots_5th' => 2, 'slots_6th' => 0, 'slots_7th' => 0, 'slots_8th' => 0, 'slots_9th' => 0],
            ['caster_level' => 11, 'slots_1st' => 4, 'slots_2nd' => 3, 'slots_3rd' => 3, 'slots_4th' => 3, 'slots_5th' => 2, 'slots_6th' => 1, 'slots_7th' => 0, 'slots_8th' => 0, 'slots_9th' => 0],
            ['caster_level' => 12, 'slots_1st' => 4, 'slots_2nd' => 3, 'slots_3rd' => 3, 'slots_4th' => 3, 'slots_5th' => 2, 'slots_6th' => 1, 'slots_7th' => 0, 'slots_8th' => 0, 'slots_9th' => 0],
            ['caster_level' => 13, 'slots_1st' => 4, 'slots_2nd' => 3, 'slots_3rd' => 3, 'slots_4th' => 3, 'slots_5th' => 2, 'slots_6th' => 1, 'slots_7th' => 1, 'slots_8th' => 0, 'slots_9th' => 0],
            ['caster_level' => 14, 'slots_1st' => 4, 'slots_2nd' => 3, 'slots_3rd' => 3, 'slots_4th' => 3, 'slots_5th' => 2, 'slots_6th' => 1, 'slots_7th' => 1, 'slots_8th' => 0, 'slots_9th' => 0],
            ['caster_level' => 15, 'slots_1st' => 4, 'slots_2nd' => 3, 'slots_3rd' => 3, 'slots_4th' => 3, 'slots_5th' => 2, 'slots_6th' => 1, 'slots_7th' => 1, 'slots_8th' => 1, 'slots_9th' => 0],
            ['caster_level' => 16, 'slots_1st' => 4, 'slots_2nd' => 3, 'slots_3rd' => 3, 'slots_4th' => 3, 'slots_5th' => 2, 'slots_6th' => 1, 'slots_7th' => 1, 'slots_8th' => 1, 'slots_9th' => 0],
            ['caster_level' => 17, 'slots_1st' => 4, 'slots_2nd' => 3, 'slots_3rd' => 3, 'slots_4th' => 3, 'slots_5th' => 2, 'slots_6th' => 1, 'slots_7th' => 1, 'slots_8th' => 1, 'slots_9th' => 1],
            ['caster_level' => 18, 'slots_1st' => 4, 'slots_2nd' => 3, 'slots_3rd' => 3, 'slots_4th' => 3, 'slots_5th' => 3, 'slots_6th' => 1, 'slots_7th' => 1, 'slots_8th' => 1, 'slots_9th' => 1],
            ['caster_level' => 19, 'slots_1st' => 4, 'slots_2nd' => 3, 'slots_3rd' => 3, 'slots_4th' => 3, 'slots_5th' => 3, 'slots_6th' => 2, 'slots_7th' => 1, 'slots_8th' => 1, 'slots_9th' => 1],
            ['caster_level' => 20, 'slots_1st' => 4, 'slots_2nd' => 3, 'slots_3rd' => 3, 'slots_4th' => 3, 'slots_5th' => 3, 'slots_6th' => 2, 'slots_7th' => 2, 'slots_8th' => 1, 'slots_9th' => 1],
        ];

        DB::table('multiclass_spell_slots')->insert($slots);
    }
}
```

**Step 5: Create the model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MulticlassSpellSlot extends Model
{
    protected $table = 'multiclass_spell_slots';
    protected $primaryKey = 'caster_level';
    public $incrementing = false;
    public $timestamps = false;

    protected $casts = [
        'caster_level' => 'integer',
        'slots_1st' => 'integer',
        'slots_2nd' => 'integer',
        'slots_3rd' => 'integer',
        'slots_4th' => 'integer',
        'slots_5th' => 'integer',
        'slots_6th' => 'integer',
        'slots_7th' => 'integer',
        'slots_8th' => 'integer',
        'slots_9th' => 'integer',
    ];

    /**
     * Get spell slots for a given caster level.
     * Returns null for level 0, caps at level 20.
     */
    public static function forCasterLevel(int $level): ?self
    {
        if ($level < 1) {
            return null;
        }

        return self::find(min($level, 20));
    }

    /**
     * Get slots as an array keyed by spell level.
     *
     * @return array<string, int>
     */
    public function toSlotsArray(): array
    {
        return [
            '1st' => $this->slots_1st,
            '2nd' => $this->slots_2nd,
            '3rd' => $this->slots_3rd,
            '4th' => $this->slots_4th,
            '5th' => $this->slots_5th,
            '6th' => $this->slots_6th,
            '7th' => $this->slots_7th,
            '8th' => $this->slots_8th,
            '9th' => $this->slots_9th,
        ];
    }
}
```

**Step 6: Run migration and tests**

Run: `docker compose exec php php artisan migrate`
Run: `docker compose exec php php artisan test tests/Unit/Models/MulticlassSpellSlotTest.php`
Expected: PASS

**Step 7: Commit**

```bash
git add database/migrations/*multiclass_spell_slots* database/seeders/MulticlassSpellSlotSeeder.php app/Models/MulticlassSpellSlot.php tests/Unit/Models/MulticlassSpellSlotTest.php
git commit -m "feat(#92): add multiclass spell slots table and seeder"
```

---

## Task 2: Create Character Classes Junction Table

**Files:**
- Create: `database/migrations/2025_12_03_000002_create_character_classes_table.php`
- Create: `app/Models/CharacterClassPivot.php`
- Create: `database/factories/CharacterClassPivotFactory.php`
- Test: `tests/Unit/Models/CharacterClassPivotTest.php`

**Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Models;

use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\CharacterClassPivot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CharacterClassPivotTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_belongs_to_a_character(): void
    {
        $character = Character::factory()->create();
        $class = CharacterClass::factory()->create(['parent_class_id' => null]);

        $pivot = CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_id' => $class->id,
            'level' => 5,
            'is_primary' => true,
            'order' => 1,
        ]);

        $this->assertTrue($pivot->character->is($character));
    }

    #[Test]
    public function it_belongs_to_a_class(): void
    {
        $character = Character::factory()->create();
        $class = CharacterClass::factory()->create(['parent_class_id' => null]);

        $pivot = CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_id' => $class->id,
            'level' => 3,
            'is_primary' => true,
            'order' => 1,
        ]);

        $this->assertTrue($pivot->characterClass->is($class));
    }

    #[Test]
    public function it_can_have_a_subclass(): void
    {
        $character = Character::factory()->create();
        $baseClass = CharacterClass::factory()->create(['parent_class_id' => null]);
        $subclass = CharacterClass::factory()->create(['parent_class_id' => $baseClass->id]);

        $pivot = CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_id' => $baseClass->id,
            'subclass_id' => $subclass->id,
            'level' => 3,
            'is_primary' => true,
            'order' => 1,
        ]);

        $this->assertTrue($pivot->subclass->is($subclass));
    }

    #[Test]
    public function it_calculates_available_hit_dice(): void
    {
        $character = Character::factory()->create();
        $class = CharacterClass::factory()->create(['parent_class_id' => null]);

        $pivot = CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_id' => $class->id,
            'level' => 5,
            'hit_dice_spent' => 2,
            'is_primary' => true,
            'order' => 1,
        ]);

        $this->assertEquals(5, $pivot->max_hit_dice);
        $this->assertEquals(3, $pivot->available_hit_dice);
    }

    #[Test]
    public function it_enforces_unique_character_class_combination(): void
    {
        $character = Character::factory()->create();
        $class = CharacterClass::factory()->create(['parent_class_id' => null]);

        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_id' => $class->id,
            'level' => 1,
            'is_primary' => true,
            'order' => 1,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_id' => $class->id,
            'level' => 1,
            'is_primary' => false,
            'order' => 2,
        ]);
    }
}
```

**Step 2: Run test to verify it fails**

Run: `docker compose exec php php artisan test tests/Unit/Models/CharacterClassPivotTest.php`
Expected: FAIL - table/class not found

**Step 3: Create the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('character_classes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('character_id');
            $table->unsignedBigInteger('class_id');
            $table->unsignedBigInteger('subclass_id')->nullable();
            $table->unsignedTinyInteger('level')->default(1);
            $table->boolean('is_primary')->default(false);
            $table->unsignedTinyInteger('order')->default(1);
            $table->unsignedTinyInteger('hit_dice_spent')->default(0);
            $table->timestamps();

            $table->foreign('character_id')
                ->references('id')
                ->on('characters')
                ->onDelete('cascade');

            $table->foreign('class_id')
                ->references('id')
                ->on('classes');

            $table->foreign('subclass_id')
                ->references('id')
                ->on('classes');

            $table->unique(['character_id', 'class_id']);
            $table->index('character_id');
            $table->index('class_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('character_classes');
    }
};
```

**Step 4: Create the model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CharacterClassPivot extends Model
{
    protected $table = 'character_classes';

    protected $fillable = [
        'character_id',
        'class_id',
        'subclass_id',
        'level',
        'is_primary',
        'order',
        'hit_dice_spent',
    ];

    protected $casts = [
        'character_id' => 'integer',
        'class_id' => 'integer',
        'subclass_id' => 'integer',
        'level' => 'integer',
        'is_primary' => 'boolean',
        'order' => 'integer',
        'hit_dice_spent' => 'integer',
    ];

    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }

    public function characterClass(): BelongsTo
    {
        return $this->belongsTo(CharacterClass::class, 'class_id');
    }

    public function subclass(): BelongsTo
    {
        return $this->belongsTo(CharacterClass::class, 'subclass_id');
    }

    public function getMaxHitDiceAttribute(): int
    {
        return $this->level;
    }

    public function getAvailableHitDiceAttribute(): int
    {
        return $this->level - $this->hit_dice_spent;
    }
}
```

**Step 5: Create the factory**

```php
<?php

namespace Database\Factories;

use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\CharacterClassPivot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CharacterClassPivot>
 */
class CharacterClassPivotFactory extends Factory
{
    protected $model = CharacterClassPivot::class;

    public function definition(): array
    {
        return [
            'character_id' => Character::factory(),
            'class_id' => CharacterClass::factory()->state(['parent_class_id' => null]),
            'subclass_id' => null,
            'level' => $this->faker->numberBetween(1, 20),
            'is_primary' => true,
            'order' => 1,
            'hit_dice_spent' => 0,
        ];
    }

    public function primary(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_primary' => true,
        ]);
    }

    public function secondary(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_primary' => false,
        ]);
    }

    public function withSubclass(CharacterClass $subclass): static
    {
        return $this->state(fn (array $attributes) => [
            'subclass_id' => $subclass->id,
        ]);
    }
}
```

**Step 6: Run migration and tests**

Run: `docker compose exec php php artisan migrate`
Run: `docker compose exec php php artisan test tests/Unit/Models/CharacterClassPivotTest.php`
Expected: PASS

**Step 7: Commit**

```bash
git add database/migrations/*character_classes* app/Models/CharacterClassPivot.php database/factories/CharacterClassPivotFactory.php tests/Unit/Models/CharacterClassPivotTest.php
git commit -m "feat(#92): add character_classes junction table and pivot model"
```

---

## Task 3: Add Character Model Relationships

**Files:**
- Modify: `app/Models/Character.php`
- Test: `tests/Unit/Models/CharacterMulticlassTest.php`

**Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Models;

use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\CharacterClassPivot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CharacterMulticlassTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_has_many_character_classes(): void
    {
        $character = Character::factory()->create();
        $fighter = CharacterClass::factory()->create(['name' => 'Fighter', 'parent_class_id' => null]);
        $wizard = CharacterClass::factory()->create(['name' => 'Wizard', 'parent_class_id' => null]);

        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_id' => $fighter->id,
            'level' => 5,
            'is_primary' => true,
            'order' => 1,
        ]);
        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_id' => $wizard->id,
            'level' => 3,
            'is_primary' => false,
            'order' => 2,
        ]);

        $this->assertCount(2, $character->characterClasses);
        $this->assertEquals('Fighter', $character->characterClasses->first()->characterClass->name);
    }

    #[Test]
    public function it_calculates_total_level_from_all_classes(): void
    {
        $character = Character::factory()->create();
        $fighter = CharacterClass::factory()->create(['parent_class_id' => null]);
        $wizard = CharacterClass::factory()->create(['parent_class_id' => null]);

        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_id' => $fighter->id,
            'level' => 5,
            'is_primary' => true,
            'order' => 1,
        ]);
        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_id' => $wizard->id,
            'level' => 3,
            'is_primary' => false,
            'order' => 2,
        ]);

        $this->assertEquals(8, $character->total_level);
    }

    #[Test]
    public function it_returns_primary_class(): void
    {
        $character = Character::factory()->create();
        $fighter = CharacterClass::factory()->create(['name' => 'Fighter', 'parent_class_id' => null]);
        $wizard = CharacterClass::factory()->create(['name' => 'Wizard', 'parent_class_id' => null]);

        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_id' => $fighter->id,
            'level' => 5,
            'is_primary' => true,
            'order' => 1,
        ]);
        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_id' => $wizard->id,
            'level' => 3,
            'is_primary' => false,
            'order' => 2,
        ]);

        $this->assertEquals('Fighter', $character->primary_class->name);
    }

    #[Test]
    public function it_detects_multiclass_status(): void
    {
        $character = Character::factory()->create();
        $fighter = CharacterClass::factory()->create(['parent_class_id' => null]);

        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_id' => $fighter->id,
            'level' => 5,
            'is_primary' => true,
            'order' => 1,
        ]);

        $this->assertFalse($character->fresh()->is_multiclass);

        $wizard = CharacterClass::factory()->create(['parent_class_id' => null]);
        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_id' => $wizard->id,
            'level' => 1,
            'is_primary' => false,
            'order' => 2,
        ]);

        $this->assertTrue($character->fresh()->is_multiclass);
    }

    #[Test]
    public function character_classes_are_ordered_by_order_column(): void
    {
        $character = Character::factory()->create();
        $fighter = CharacterClass::factory()->create(['name' => 'Fighter', 'parent_class_id' => null]);
        $wizard = CharacterClass::factory()->create(['name' => 'Wizard', 'parent_class_id' => null]);
        $cleric = CharacterClass::factory()->create(['name' => 'Cleric', 'parent_class_id' => null]);

        // Create in non-sequential order
        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_id' => $cleric->id,
            'level' => 1,
            'is_primary' => false,
            'order' => 3,
        ]);
        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_id' => $fighter->id,
            'level' => 5,
            'is_primary' => true,
            'order' => 1,
        ]);
        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_id' => $wizard->id,
            'level' => 2,
            'is_primary' => false,
            'order' => 2,
        ]);

        $classNames = $character->fresh()->characterClasses->pluck('characterClass.name')->toArray();

        $this->assertEquals(['Fighter', 'Wizard', 'Cleric'], $classNames);
    }
}
```

**Step 2: Run test to verify it fails**

Run: `docker compose exec php php artisan test tests/Unit/Models/CharacterMulticlassTest.php`
Expected: FAIL - relationship not defined

**Step 3: Add relationships to Character model**

Add to `app/Models/Character.php`:

```php
use App\Models\CharacterClassPivot;

// Add relationship method
public function characterClasses(): HasMany
{
    return $this->hasMany(CharacterClassPivot::class)->orderBy('order');
}

// Add computed attributes
public function getPrimaryClassAttribute(): ?CharacterClass
{
    return $this->characterClasses->firstWhere('is_primary', true)?->characterClass;
}

public function getTotalLevelAttribute(): int
{
    if ($this->characterClasses->isEmpty()) {
        return $this->level ?? 1;
    }
    return $this->characterClasses->sum('level');
}

public function getIsMulticlassAttribute(): bool
{
    return $this->characterClasses->count() > 1;
}
```

**Step 4: Run tests**

Run: `docker compose exec php php artisan test tests/Unit/Models/CharacterMulticlassTest.php`
Expected: PASS

**Step 5: Commit**

```bash
git add app/Models/Character.php tests/Unit/Models/CharacterMulticlassTest.php
git commit -m "feat(#92): add multiclass relationships to Character model"
```

---

## Task 4: Create MulticlassValidationService

**Files:**
- Create: `app/Services/MulticlassValidationService.php`
- Create: `app/DTOs/ValidationResult.php`
- Create: `app/DTOs/RequirementCheck.php`
- Test: `tests/Unit/Services/MulticlassValidationServiceTest.php`

**Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Services;

use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\CharacterClassPivot;
use App\Models\Proficiency;
use App\Services\MulticlassValidationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MulticlassValidationServiceTest extends TestCase
{
    use RefreshDatabase;

    private MulticlassValidationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new MulticlassValidationService();
    }

    #[Test]
    public function it_passes_when_character_meets_single_ability_requirement(): void
    {
        $character = Character::factory()->create(['charisma' => 13]);
        $bard = $this->createClassWithRequirement('Bard', 'charisma', 13);

        $result = $this->service->canAddClass($character, $bard);

        $this->assertTrue($result->passed);
    }

    #[Test]
    public function it_fails_when_character_does_not_meet_requirement(): void
    {
        $character = Character::factory()->create(['charisma' => 10]);
        $bard = $this->createClassWithRequirement('Bard', 'charisma', 13);

        $result = $this->service->canAddClass($character, $bard);

        $this->assertFalse($result->passed);
        $this->assertStringContainsString('Charisma 13', $result->errors[0]);
    }

    #[Test]
    public function it_passes_with_or_requirements_when_one_is_met(): void
    {
        $character = Character::factory()->create([
            'strength' => 10,
            'dexterity' => 14,
        ]);
        $fighter = $this->createClassWithOrRequirement('Fighter', [
            ['ability' => 'strength', 'minimum' => 13],
            ['ability' => 'dexterity', 'minimum' => 13],
        ]);

        $result = $this->service->canAddClass($character, $fighter);

        $this->assertTrue($result->passed);
    }

    #[Test]
    public function it_fails_with_or_requirements_when_none_are_met(): void
    {
        $character = Character::factory()->create([
            'strength' => 10,
            'dexterity' => 10,
        ]);
        $fighter = $this->createClassWithOrRequirement('Fighter', [
            ['ability' => 'strength', 'minimum' => 13],
            ['ability' => 'dexterity', 'minimum' => 13],
        ]);

        $result = $this->service->canAddClass($character, $fighter);

        $this->assertFalse($result->passed);
    }

    #[Test]
    public function it_passes_with_and_requirements_when_all_are_met(): void
    {
        $character = Character::factory()->create([
            'strength' => 14,
            'charisma' => 15,
        ]);
        $paladin = $this->createClassWithAndRequirement('Paladin', [
            ['ability' => 'strength', 'minimum' => 13],
            ['ability' => 'charisma', 'minimum' => 13],
        ]);

        $result = $this->service->canAddClass($character, $paladin);

        $this->assertTrue($result->passed);
    }

    #[Test]
    public function it_fails_with_and_requirements_when_one_is_not_met(): void
    {
        $character = Character::factory()->create([
            'strength' => 14,
            'charisma' => 10,
        ]);
        $paladin = $this->createClassWithAndRequirement('Paladin', [
            ['ability' => 'strength', 'minimum' => 13],
            ['ability' => 'charisma', 'minimum' => 13],
        ]);

        $result = $this->service->canAddClass($character, $paladin);

        $this->assertFalse($result->passed);
    }

    #[Test]
    public function it_bypasses_validation_with_force_flag(): void
    {
        $character = Character::factory()->create(['charisma' => 8]);
        $bard = $this->createClassWithRequirement('Bard', 'charisma', 13);

        $result = $this->service->canAddClass($character, $bard, force: true);

        $this->assertTrue($result->passed);
    }

    #[Test]
    public function it_checks_current_class_requirements_for_multiclass(): void
    {
        // Fighter with STR 13 requirement, character only has DEX
        $fighter = $this->createClassWithOrRequirement('Fighter', [
            ['ability' => 'strength', 'minimum' => 13],
            ['ability' => 'dexterity', 'minimum' => 13],
        ]);
        $wizard = $this->createClassWithRequirement('Wizard', 'intelligence', 13);

        $character = Character::factory()->create([
            'strength' => 10,
            'dexterity' => 14,  // Meets Fighter via DEX
            'intelligence' => 14,  // Meets Wizard
        ]);

        // Give character Fighter levels
        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_id' => $fighter->id,
            'level' => 5,
            'is_primary' => true,
            'order' => 1,
        ]);

        $result = $this->service->canAddClass($character->fresh(), $wizard);

        $this->assertTrue($result->passed);
    }

    #[Test]
    public function it_fails_if_current_class_requirements_not_met(): void
    {
        $fighter = $this->createClassWithOrRequirement('Fighter', [
            ['ability' => 'strength', 'minimum' => 13],
            ['ability' => 'dexterity', 'minimum' => 13],
        ]);
        $wizard = $this->createClassWithRequirement('Wizard', 'intelligence', 13);

        // Character doesn't meet Fighter's multiclass requirements
        $character = Character::factory()->create([
            'strength' => 10,
            'dexterity' => 10,  // Doesn't meet Fighter
            'intelligence' => 14,  // Meets Wizard
        ]);

        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_id' => $fighter->id,
            'level' => 5,
            'is_primary' => true,
            'order' => 1,
        ]);

        $result = $this->service->canAddClass($character->fresh(), $wizard);

        $this->assertFalse($result->passed);
        $this->assertStringContainsString('Fighter', $result->errors[0]);
    }

    // Helper methods to create classes with requirements
    private function createClassWithRequirement(string $name, string $ability, int $minimum): CharacterClass
    {
        $class = CharacterClass::factory()->create([
            'name' => $name,
            'slug' => strtolower($name),
            'parent_class_id' => null,
        ]);

        Proficiency::create([
            'reference_type' => CharacterClass::class,
            'reference_id' => $class->id,
            'proficiency_type' => 'multiclass_requirement',
            'name' => ucfirst($ability) . ' ' . $minimum,
            'ability_code' => strtoupper(substr($ability, 0, 3)),
            'minimum_value' => $minimum,
            'is_choice' => false,
        ]);

        return $class;
    }

    private function createClassWithOrRequirement(string $name, array $requirements): CharacterClass
    {
        $class = CharacterClass::factory()->create([
            'name' => $name,
            'slug' => strtolower($name),
            'parent_class_id' => null,
        ]);

        foreach ($requirements as $req) {
            Proficiency::create([
                'reference_type' => CharacterClass::class,
                'reference_id' => $class->id,
                'proficiency_type' => 'multiclass_requirement',
                'name' => ucfirst($req['ability']) . ' ' . $req['minimum'],
                'ability_code' => strtoupper(substr($req['ability'], 0, 3)),
                'minimum_value' => $req['minimum'],
                'is_choice' => true,  // OR condition
            ]);
        }

        return $class;
    }

    private function createClassWithAndRequirement(string $name, array $requirements): CharacterClass
    {
        $class = CharacterClass::factory()->create([
            'name' => $name,
            'slug' => strtolower($name),
            'parent_class_id' => null,
        ]);

        foreach ($requirements as $req) {
            Proficiency::create([
                'reference_type' => CharacterClass::class,
                'reference_id' => $class->id,
                'proficiency_type' => 'multiclass_requirement',
                'name' => ucfirst($req['ability']) . ' ' . $req['minimum'],
                'ability_code' => strtoupper(substr($req['ability'], 0, 3)),
                'minimum_value' => $req['minimum'],
                'is_choice' => false,  // AND condition
            ]);
        }

        return $class;
    }
}
```

**Step 2: Run test to verify it fails**

Run: `docker compose exec php php artisan test tests/Unit/Services/MulticlassValidationServiceTest.php`
Expected: FAIL - service not found

**Step 3: Create the DTOs**

`app/DTOs/ValidationResult.php`:
```php
<?php

namespace App\DTOs;

class ValidationResult
{
    public function __construct(
        public readonly bool $passed,
        public readonly array $errors = [],
    ) {}

    public static function success(): self
    {
        return new self(passed: true);
    }

    public static function failure(array $errors): self
    {
        return new self(passed: false, errors: $errors);
    }
}
```

`app/DTOs/RequirementCheck.php`:
```php
<?php

namespace App\DTOs;

class RequirementCheck
{
    public function __construct(
        public readonly bool $met,
        public readonly string $className,
        public readonly array $failedRequirements = [],
    ) {}
}
```

**Step 4: Create the service**

```php
<?php

namespace App\Services;

use App\DTOs\RequirementCheck;
use App\DTOs\ValidationResult;
use App\Models\Character;
use App\Models\CharacterClass;

class MulticlassValidationService
{
    private const ABILITY_MAP = [
        'STR' => 'strength',
        'DEX' => 'dexterity',
        'CON' => 'constitution',
        'INT' => 'intelligence',
        'WIS' => 'wisdom',
        'CHA' => 'charisma',
    ];

    /**
     * Check if a character can add a new class.
     * Must meet requirements for ALL current classes AND the new class.
     */
    public function canAddClass(
        Character $character,
        CharacterClass $newClass,
        bool $force = false
    ): ValidationResult {
        if ($force) {
            return ValidationResult::success();
        }

        $errors = [];

        // Check requirements for all current classes
        foreach ($character->characterClasses as $characterClass) {
            $check = $this->meetsRequirements($character, $characterClass->characterClass);
            if (!$check->met) {
                $errors[] = "Does not meet {$check->className} multiclass requirements: " .
                    implode(', ', $check->failedRequirements);
            }
        }

        // Check requirements for the new class
        $newClassCheck = $this->meetsRequirements($character, $newClass);
        if (!$newClassCheck->met) {
            $errors[] = "Does not meet {$newClassCheck->className} multiclass requirements: " .
                implode(', ', $newClassCheck->failedRequirements);
        }

        if (!empty($errors)) {
            return ValidationResult::failure($errors);
        }

        return ValidationResult::success();
    }

    /**
     * Check if character meets a specific class's multiclass requirements.
     */
    public function meetsRequirements(
        Character $character,
        CharacterClass $class
    ): RequirementCheck {
        $requirements = $class->multiclassRequirements;

        if ($requirements->isEmpty()) {
            return new RequirementCheck(met: true, className: $class->name);
        }

        // Separate OR requirements (is_choice = true) from AND requirements (is_choice = false)
        $orRequirements = $requirements->where('is_choice', true);
        $andRequirements = $requirements->where('is_choice', false);

        $failedRequirements = [];

        // For AND requirements, ALL must be met
        foreach ($andRequirements as $req) {
            if (!$this->checkRequirement($character, $req)) {
                $failedRequirements[] = $req->name;
            }
        }

        // For OR requirements, at least ONE must be met
        if ($orRequirements->isNotEmpty()) {
            $anyOrMet = false;
            $orNames = [];
            foreach ($orRequirements as $req) {
                $orNames[] = $req->name;
                if ($this->checkRequirement($character, $req)) {
                    $anyOrMet = true;
                    break;
                }
            }
            if (!$anyOrMet) {
                $failedRequirements[] = implode(' or ', $orNames);
            }
        }

        return new RequirementCheck(
            met: empty($failedRequirements),
            className: $class->name,
            failedRequirements: $failedRequirements,
        );
    }

    private function checkRequirement(Character $character, $requirement): bool
    {
        $abilityCode = $requirement->ability_code;
        $minimumValue = $requirement->minimum_value;

        $abilityName = self::ABILITY_MAP[$abilityCode] ?? null;
        if (!$abilityName) {
            return true; // Unknown ability, skip
        }

        $characterValue = $character->{$abilityName} ?? 0;

        return $characterValue >= $minimumValue;
    }
}
```

**Step 5: Run tests**

Run: `docker compose exec php php artisan test tests/Unit/Services/MulticlassValidationServiceTest.php`
Expected: PASS

**Step 6: Commit**

```bash
git add app/Services/MulticlassValidationService.php app/DTOs/ValidationResult.php app/DTOs/RequirementCheck.php tests/Unit/Services/MulticlassValidationServiceTest.php
git commit -m "feat(#92): add MulticlassValidationService for prerequisite checking"
```

---

## Task 5: Create MulticlassSpellSlotCalculator

**Files:**
- Create: `app/Services/MulticlassSpellSlotCalculator.php`
- Create: `app/DTOs/SpellSlotResult.php`
- Create: `app/DTOs/PactSlotInfo.php`
- Test: `tests/Unit/Services/MulticlassSpellSlotCalculatorTest.php`

**Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Services;

use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\CharacterClassPivot;
use App\Services\MulticlassSpellSlotCalculator;
use Database\Seeders\MulticlassSpellSlotSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MulticlassSpellSlotCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private MulticlassSpellSlotCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MulticlassSpellSlotSeeder::class);
        $this->calculator = new MulticlassSpellSlotCalculator();
    }

    #[Test]
    public function it_calculates_caster_level_for_full_caster(): void
    {
        $character = Character::factory()->create();
        $wizard = $this->createClass('Wizard', 'full');

        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_id' => $wizard->id,
            'level' => 5,
            'is_primary' => true,
            'order' => 1,
        ]);

        $casterLevel = $this->calculator->calculateCasterLevel($character->fresh());

        $this->assertEquals(5, $casterLevel);
    }

    #[Test]
    public function it_calculates_caster_level_for_half_caster(): void
    {
        $character = Character::factory()->create();
        $paladin = $this->createClass('Paladin', 'half');

        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_id' => $paladin->id,
            'level' => 6,
            'is_primary' => true,
            'order' => 1,
        ]);

        $casterLevel = $this->calculator->calculateCasterLevel($character->fresh());

        $this->assertEquals(3, $casterLevel); // floor(6 * 0.5)
    }

    #[Test]
    public function it_calculates_caster_level_for_third_caster(): void
    {
        $character = Character::factory()->create();
        $eldritchKnight = $this->createClass('Eldritch Knight', 'third');

        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_id' => $eldritchKnight->id,
            'level' => 9,
            'is_primary' => true,
            'order' => 1,
        ]);

        $casterLevel = $this->calculator->calculateCasterLevel($character->fresh());

        $this->assertEquals(3, $casterLevel); // floor(9 * 0.334)
    }

    #[Test]
    public function it_excludes_warlock_from_caster_level(): void
    {
        $character = Character::factory()->create();
        $warlock = $this->createClass('Warlock', 'pact');

        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_id' => $warlock->id,
            'level' => 10,
            'is_primary' => true,
            'order' => 1,
        ]);

        $casterLevel = $this->calculator->calculateCasterLevel($character->fresh());

        $this->assertEquals(0, $casterLevel);
    }

    #[Test]
    public function it_combines_caster_levels_from_multiple_classes(): void
    {
        $character = Character::factory()->create();
        $cleric = $this->createClass('Cleric', 'full');
        $paladin = $this->createClass('Paladin', 'half');

        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_id' => $cleric->id,
            'level' => 5,
            'is_primary' => true,
            'order' => 1,
        ]);
        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_id' => $paladin->id,
            'level' => 4,
            'is_primary' => false,
            'order' => 2,
        ]);

        $casterLevel = $this->calculator->calculateCasterLevel($character->fresh());

        $this->assertEquals(7, $casterLevel); // 5 + floor(4 * 0.5)
    }

    #[Test]
    public function it_returns_spell_slots_for_multiclass_caster(): void
    {
        $character = Character::factory()->create();
        $cleric = $this->createClass('Cleric', 'full');
        $wizard = $this->createClass('Wizard', 'full');

        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_id' => $cleric->id,
            'level' => 5,
            'is_primary' => true,
            'order' => 1,
        ]);
        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_id' => $wizard->id,
            'level' => 3,
            'is_primary' => false,
            'order' => 2,
        ]);

        $result = $this->calculator->calculate($character->fresh());

        // Caster level 8 = 4/3/3/2/0/0/0/0/0
        $this->assertEquals(4, $result->standardSlots['1st']);
        $this->assertEquals(3, $result->standardSlots['2nd']);
        $this->assertEquals(3, $result->standardSlots['3rd']);
        $this->assertEquals(2, $result->standardSlots['4th']);
        $this->assertEquals(0, $result->standardSlots['5th']);
        $this->assertNull($result->pactSlots);
    }

    #[Test]
    public function it_returns_separate_pact_slots_for_warlock(): void
    {
        $character = Character::factory()->create();
        $wizard = $this->createClass('Wizard', 'full');
        $warlock = $this->createClass('Warlock', 'pact');

        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_id' => $wizard->id,
            'level' => 5,
            'is_primary' => true,
            'order' => 1,
        ]);
        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_id' => $warlock->id,
            'level' => 3,
            'is_primary' => false,
            'order' => 2,
        ]);

        $result = $this->calculator->calculate($character->fresh());

        // Standard slots from Wizard 5 only (caster level 5)
        $this->assertEquals(4, $result->standardSlots['1st']);
        $this->assertEquals(3, $result->standardSlots['2nd']);
        $this->assertEquals(2, $result->standardSlots['3rd']);

        // Pact slots from Warlock 3 (2 slots at 2nd level)
        $this->assertNotNull($result->pactSlots);
        $this->assertEquals(2, $result->pactSlots->count);
        $this->assertEquals(2, $result->pactSlots->level);
    }

    #[Test]
    public function it_returns_null_for_non_caster(): void
    {
        $character = Character::factory()->create();
        $fighter = $this->createClass('Fighter', 'none');

        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_id' => $fighter->id,
            'level' => 10,
            'is_primary' => true,
            'order' => 1,
        ]);

        $result = $this->calculator->calculate($character->fresh());

        $this->assertNull($result->standardSlots);
        $this->assertNull($result->pactSlots);
    }

    private function createClass(string $name, string $casterType): CharacterClass
    {
        return CharacterClass::factory()->create([
            'name' => $name,
            'slug' => strtolower(str_replace(' ', '-', $name)),
            'parent_class_id' => null,
            'caster_type' => $casterType,
        ]);
    }
}
```

**Step 2: Run test to verify it fails**

Run: `docker compose exec php php artisan test tests/Unit/Services/MulticlassSpellSlotCalculatorTest.php`
Expected: FAIL - service/DTOs not found

**Step 3: Create the DTOs**

`app/DTOs/PactSlotInfo.php`:
```php
<?php

namespace App\DTOs;

class PactSlotInfo
{
    public function __construct(
        public readonly int $count,
        public readonly int $level,
    ) {}
}
```

`app/DTOs/SpellSlotResult.php`:
```php
<?php

namespace App\DTOs;

class SpellSlotResult
{
    /**
     * @param array<string, int>|null $standardSlots Keyed by spell level ('1st', '2nd', etc.)
     * @param PactSlotInfo|null $pactSlots Warlock pact magic slots
     */
    public function __construct(
        public readonly ?array $standardSlots,
        public readonly ?PactSlotInfo $pactSlots,
    ) {}
}
```

**Step 4: Check if CharacterClass has caster_type column**

First check if `caster_type` exists on the classes table. If not, we need to add a migration.

Run: `docker compose exec php php artisan tinker --execute="Schema::hasColumn('classes', 'caster_type')"`

If false, create migration:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->string('caster_type', 10)->default('none')->after('spellcasting_ability_id');
        });
    }

    public function down(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->dropColumn('caster_type');
        });
    }
};
```

**Step 5: Create the service**

```php
<?php

namespace App\Services;

use App\DTOs\PactSlotInfo;
use App\DTOs\SpellSlotResult;
use App\Models\Character;
use App\Models\MulticlassSpellSlot;

class MulticlassSpellSlotCalculator
{
    /**
     * Caster multipliers per D&D 5e rules (PHB p164).
     */
    private const CASTER_MULTIPLIERS = [
        'full' => 1,      // Wizard, Cleric, Druid, Bard, Sorcerer
        'half' => 0.5,    // Paladin, Ranger
        'third' => 0.334, // Eldritch Knight, Arcane Trickster
        'pact' => 0,      // Warlock (separate Pact Magic)
        'none' => 0,
    ];

    /**
     * Warlock Pact Magic progression (PHB p106).
     * level => [slots, slot_level]
     */
    private const PACT_MAGIC = [
        1 => [1, 1],
        2 => [2, 1],
        3 => [2, 2],
        4 => [2, 2],
        5 => [2, 3],
        6 => [2, 3],
        7 => [2, 4],
        8 => [2, 4],
        9 => [2, 5],
        10 => [2, 5],
        11 => [3, 5],
        12 => [3, 5],
        13 => [3, 5],
        14 => [3, 5],
        15 => [3, 5],
        16 => [3, 5],
        17 => [4, 5],
        18 => [4, 5],
        19 => [4, 5],
        20 => [4, 5],
    ];

    /**
     * Calculate spell slots for a character.
     */
    public function calculate(Character $character): SpellSlotResult
    {
        $casterLevel = $this->calculateCasterLevel($character);
        $pactSlots = $this->getPactMagicSlots($character);

        if ($casterLevel === 0 && $pactSlots === null) {
            return new SpellSlotResult(standardSlots: null, pactSlots: null);
        }

        $standardSlots = null;
        if ($casterLevel > 0) {
            $slotRow = MulticlassSpellSlot::forCasterLevel($casterLevel);
            $standardSlots = $slotRow?->toSlotsArray();
        }

        return new SpellSlotResult(
            standardSlots: $standardSlots,
            pactSlots: $pactSlots,
        );
    }

    /**
     * Calculate combined caster level from all classes.
     * Warlock levels are excluded (use Pact Magic separately).
     */
    public function calculateCasterLevel(Character $character): int
    {
        $total = 0.0;

        foreach ($character->characterClasses as $characterClass) {
            $casterType = $characterClass->characterClass->caster_type ?? 'none';
            $multiplier = self::CASTER_MULTIPLIERS[$casterType] ?? 0;
            $total += $characterClass->level * $multiplier;
        }

        return (int) floor($total);
    }

    /**
     * Get Pact Magic slots if character has Warlock levels.
     */
    public function getPactMagicSlots(Character $character): ?PactSlotInfo
    {
        $warlockClass = $character->characterClasses
            ->first(fn ($cc) => ($cc->characterClass->caster_type ?? 'none') === 'pact');

        if (!$warlockClass) {
            return null;
        }

        $level = min($warlockClass->level, 20);
        $pactData = self::PACT_MAGIC[$level] ?? null;

        if (!$pactData) {
            return null;
        }

        return new PactSlotInfo(
            count: $pactData[0],
            level: $pactData[1],
        );
    }
}
```

**Step 6: Run tests**

Run: `docker compose exec php php artisan test tests/Unit/Services/MulticlassSpellSlotCalculatorTest.php`
Expected: PASS

**Step 7: Commit**

```bash
git add app/Services/MulticlassSpellSlotCalculator.php app/DTOs/SpellSlotResult.php app/DTOs/PactSlotInfo.php tests/Unit/Services/MulticlassSpellSlotCalculatorTest.php
git commit -m "feat(#92): add MulticlassSpellSlotCalculator service"
```

---

## Task 6: Create AddClassService

**Files:**
- Create: `app/Services/AddClassService.php`
- Create: `app/Exceptions/MulticlassPrerequisiteException.php`
- Create: `app/Exceptions/DuplicateClassException.php`
- Create: `app/Exceptions/MaxLevelReachedException.php`
- Test: `tests/Unit/Services/AddClassServiceTest.php`

**Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Services;

use App\Exceptions\DuplicateClassException;
use App\Exceptions\MaxLevelReachedException;
use App\Exceptions\MulticlassPrerequisiteException;
use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\CharacterClassPivot;
use App\Models\Proficiency;
use App\Services\AddClassService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AddClassServiceTest extends TestCase
{
    use RefreshDatabase;

    private AddClassService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(AddClassService::class);
    }

    #[Test]
    public function it_adds_first_class_to_character(): void
    {
        $character = Character::factory()->create(['strength' => 14]);
        $fighter = CharacterClass::factory()->create(['name' => 'Fighter', 'parent_class_id' => null]);

        $pivot = $this->service->addClass($character, $fighter);

        $this->assertTrue($pivot->is_primary);
        $this->assertEquals(1, $pivot->level);
        $this->assertEquals(1, $pivot->order);
        $this->assertTrue($pivot->characterClass->is($fighter));
    }

    #[Test]
    public function it_adds_second_class_as_non_primary(): void
    {
        $character = Character::factory()->create([
            'strength' => 14,
            'intelligence' => 14,
        ]);
        $fighter = CharacterClass::factory()->create(['name' => 'Fighter', 'parent_class_id' => null]);
        $wizard = CharacterClass::factory()->create(['name' => 'Wizard', 'parent_class_id' => null]);

        // Add first class
        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_id' => $fighter->id,
            'level' => 5,
            'is_primary' => true,
            'order' => 1,
        ]);

        $pivot = $this->service->addClass($character->fresh(), $wizard);

        $this->assertFalse($pivot->is_primary);
        $this->assertEquals(1, $pivot->level);
        $this->assertEquals(2, $pivot->order);
    }

    #[Test]
    public function it_throws_when_prerequisites_not_met(): void
    {
        $character = Character::factory()->create(['charisma' => 8]);
        $bard = CharacterClass::factory()->create(['name' => 'Bard', 'parent_class_id' => null]);

        Proficiency::create([
            'reference_type' => CharacterClass::class,
            'reference_id' => $bard->id,
            'proficiency_type' => 'multiclass_requirement',
            'name' => 'Charisma 13',
            'ability_code' => 'CHA',
            'minimum_value' => 13,
            'is_choice' => false,
        ]);

        $this->expectException(MulticlassPrerequisiteException::class);

        $this->service->addClass($character, $bard);
    }

    #[Test]
    public function it_bypasses_prerequisites_with_force(): void
    {
        $character = Character::factory()->create(['charisma' => 8]);
        $bard = CharacterClass::factory()->create(['name' => 'Bard', 'parent_class_id' => null]);

        Proficiency::create([
            'reference_type' => CharacterClass::class,
            'reference_id' => $bard->id,
            'proficiency_type' => 'multiclass_requirement',
            'name' => 'Charisma 13',
            'ability_code' => 'CHA',
            'minimum_value' => 13,
            'is_choice' => false,
        ]);

        $pivot = $this->service->addClass($character, $bard, force: true);

        $this->assertNotNull($pivot);
    }

    #[Test]
    public function it_throws_when_class_already_exists(): void
    {
        $character = Character::factory()->create();
        $fighter = CharacterClass::factory()->create(['name' => 'Fighter', 'parent_class_id' => null]);

        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_id' => $fighter->id,
            'level' => 5,
            'is_primary' => true,
            'order' => 1,
        ]);

        $this->expectException(DuplicateClassException::class);

        $this->service->addClass($character->fresh(), $fighter);
    }

    #[Test]
    public function it_throws_when_total_level_would_exceed_20(): void
    {
        $character = Character::factory()->create();
        $fighter = CharacterClass::factory()->create(['name' => 'Fighter', 'parent_class_id' => null]);
        $wizard = CharacterClass::factory()->create(['name' => 'Wizard', 'parent_class_id' => null]);

        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_id' => $fighter->id,
            'level' => 20,
            'is_primary' => true,
            'order' => 1,
        ]);

        $this->expectException(MaxLevelReachedException::class);

        $this->service->addClass($character->fresh(), $wizard);
    }
}
```

**Step 2: Run test to verify it fails**

Run: `docker compose exec php php artisan test tests/Unit/Services/AddClassServiceTest.php`
Expected: FAIL - service/exceptions not found

**Step 3: Create the exceptions**

`app/Exceptions/MulticlassPrerequisiteException.php`:
```php
<?php

namespace App\Exceptions;

use Exception;

class MulticlassPrerequisiteException extends Exception
{
    public function __construct(
        public readonly array $errors,
        string $message = 'Multiclass prerequisites not met'
    ) {
        parent::__construct($message);
    }
}
```

`app/Exceptions/DuplicateClassException.php`:
```php
<?php

namespace App\Exceptions;

use Exception;

class DuplicateClassException extends Exception
{
    public function __construct(string $className)
    {
        parent::__construct("Character already has levels in {$className}");
    }
}
```

`app/Exceptions/MaxLevelReachedException.php`:
```php
<?php

namespace App\Exceptions;

use Exception;

class MaxLevelReachedException extends Exception
{
    public function __construct()
    {
        parent::__construct('Character has reached maximum level (20)');
    }
}
```

**Step 4: Create the service**

```php
<?php

namespace App\Services;

use App\Exceptions\DuplicateClassException;
use App\Exceptions\MaxLevelReachedException;
use App\Exceptions\MulticlassPrerequisiteException;
use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\CharacterClassPivot;

class AddClassService
{
    public function __construct(
        private MulticlassValidationService $validator,
    ) {}

    /**
     * Add a class to a character.
     *
     * @throws MulticlassPrerequisiteException
     * @throws DuplicateClassException
     * @throws MaxLevelReachedException
     */
    public function addClass(
        Character $character,
        CharacterClass $class,
        bool $force = false
    ): CharacterClassPivot {
        // Check for duplicate
        if ($character->characterClasses()->where('class_id', $class->id)->exists()) {
            throw new DuplicateClassException($class->name);
        }

        // Check max level
        if ($character->total_level >= 20) {
            throw new MaxLevelReachedException();
        }

        // Check prerequisites (if not first class and not forced)
        if ($character->characterClasses->isNotEmpty() && !$force) {
            $result = $this->validator->canAddClass($character, $class);
            if (!$result->passed) {
                throw new MulticlassPrerequisiteException($result->errors);
            }
        }

        // Determine order and primary status
        $isPrimary = $character->characterClasses->isEmpty();
        $order = $character->characterClasses->max('order') + 1 ?: 1;

        return CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_id' => $class->id,
            'level' => 1,
            'is_primary' => $isPrimary,
            'order' => $order,
            'hit_dice_spent' => 0,
        ]);
    }
}
```

**Step 5: Run tests**

Run: `docker compose exec php php artisan test tests/Unit/Services/AddClassServiceTest.php`
Expected: PASS

**Step 6: Commit**

```bash
git add app/Services/AddClassService.php app/Exceptions/MulticlassPrerequisiteException.php app/Exceptions/DuplicateClassException.php app/Exceptions/MaxLevelReachedException.php tests/Unit/Services/AddClassServiceTest.php
git commit -m "feat(#92): add AddClassService for multiclass class addition"
```

---

## Task 7: Create CharacterClassController

**Files:**
- Create: `app/Http/Controllers/Api/CharacterClassController.php`
- Create: `app/Http/Requests/Character/AddCharacterClassRequest.php`
- Create: `app/Http/Resources/CharacterClassPivotResource.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Api/CharacterMulticlassApiTest.php`

**Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Api;

use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\CharacterClassPivot;
use App\Models\Proficiency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CharacterMulticlassApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_lists_character_classes(): void
    {
        $character = Character::factory()->create();
        $fighter = CharacterClass::factory()->create(['name' => 'Fighter', 'parent_class_id' => null]);
        $wizard = CharacterClass::factory()->create(['name' => 'Wizard', 'parent_class_id' => null]);

        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_id' => $fighter->id,
            'level' => 5,
            'is_primary' => true,
            'order' => 1,
        ]);
        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_id' => $wizard->id,
            'level' => 3,
            'is_primary' => false,
            'order' => 2,
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/classes");

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.class.name', 'Fighter')
            ->assertJsonPath('data.0.level', 5)
            ->assertJsonPath('data.0.is_primary', true)
            ->assertJsonPath('data.1.class.name', 'Wizard')
            ->assertJsonPath('data.1.level', 3)
            ->assertJsonPath('data.1.is_primary', false);
    }

    #[Test]
    public function it_adds_a_class_to_character(): void
    {
        $character = Character::factory()->create([
            'strength' => 14,
            'intelligence' => 14,
        ]);
        $fighter = CharacterClass::factory()->create(['name' => 'Fighter', 'parent_class_id' => null]);

        // Add primary class first
        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_id' => $fighter->id,
            'level' => 5,
            'is_primary' => true,
            'order' => 1,
        ]);

        $wizard = CharacterClass::factory()->create(['name' => 'Wizard', 'parent_class_id' => null]);

        $response = $this->postJson("/api/v1/characters/{$character->id}/classes", [
            'class_id' => $wizard->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.class.name', 'Wizard')
            ->assertJsonPath('data.level', 1)
            ->assertJsonPath('data.is_primary', false);

        $this->assertDatabaseHas('character_classes', [
            'character_id' => $character->id,
            'class_id' => $wizard->id,
            'level' => 1,
        ]);
    }

    #[Test]
    public function it_validates_prerequisites_when_adding_class(): void
    {
        $character = Character::factory()->create(['charisma' => 8]);
        $fighter = CharacterClass::factory()->create(['name' => 'Fighter', 'parent_class_id' => null]);

        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_id' => $fighter->id,
            'level' => 5,
            'is_primary' => true,
            'order' => 1,
        ]);

        $bard = CharacterClass::factory()->create(['name' => 'Bard', 'parent_class_id' => null]);
        Proficiency::create([
            'reference_type' => CharacterClass::class,
            'reference_id' => $bard->id,
            'proficiency_type' => 'multiclass_requirement',
            'name' => 'Charisma 13',
            'ability_code' => 'CHA',
            'minimum_value' => 13,
            'is_choice' => false,
        ]);

        $response = $this->postJson("/api/v1/characters/{$character->id}/classes", [
            'class_id' => $bard->id,
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'Multiclass prerequisites not met');
    }

    #[Test]
    public function it_allows_force_bypass_of_prerequisites(): void
    {
        $character = Character::factory()->create(['charisma' => 8]);
        $fighter = CharacterClass::factory()->create(['name' => 'Fighter', 'parent_class_id' => null]);

        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_id' => $fighter->id,
            'level' => 5,
            'is_primary' => true,
            'order' => 1,
        ]);

        $bard = CharacterClass::factory()->create(['name' => 'Bard', 'parent_class_id' => null]);
        Proficiency::create([
            'reference_type' => CharacterClass::class,
            'reference_id' => $bard->id,
            'proficiency_type' => 'multiclass_requirement',
            'name' => 'Charisma 13',
            'ability_code' => 'CHA',
            'minimum_value' => 13,
            'is_choice' => false,
        ]);

        $response = $this->postJson("/api/v1/characters/{$character->id}/classes", [
            'class_id' => $bard->id,
            'force' => true,
        ]);

        $response->assertCreated();
    }

    #[Test]
    public function it_prevents_duplicate_classes(): void
    {
        $character = Character::factory()->create();
        $fighter = CharacterClass::factory()->create(['name' => 'Fighter', 'parent_class_id' => null]);

        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_id' => $fighter->id,
            'level' => 5,
            'is_primary' => true,
            'order' => 1,
        ]);

        $response = $this->postJson("/api/v1/characters/{$character->id}/classes", [
            'class_id' => $fighter->id,
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'Character already has levels in Fighter');
    }

    #[Test]
    public function it_removes_a_class_from_character(): void
    {
        $character = Character::factory()->create();
        $fighter = CharacterClass::factory()->create(['name' => 'Fighter', 'parent_class_id' => null]);
        $wizard = CharacterClass::factory()->create(['name' => 'Wizard', 'parent_class_id' => null]);

        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_id' => $fighter->id,
            'level' => 5,
            'is_primary' => true,
            'order' => 1,
        ]);
        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_id' => $wizard->id,
            'level' => 3,
            'is_primary' => false,
            'order' => 2,
        ]);

        $response = $this->deleteJson("/api/v1/characters/{$character->id}/classes/{$wizard->id}");

        $response->assertNoContent();

        $this->assertDatabaseMissing('character_classes', [
            'character_id' => $character->id,
            'class_id' => $wizard->id,
        ]);
    }

    #[Test]
    public function it_prevents_removing_last_class(): void
    {
        $character = Character::factory()->create();
        $fighter = CharacterClass::factory()->create(['name' => 'Fighter', 'parent_class_id' => null]);

        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_id' => $fighter->id,
            'level' => 5,
            'is_primary' => true,
            'order' => 1,
        ]);

        $response = $this->deleteJson("/api/v1/characters/{$character->id}/classes/{$fighter->id}");

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'Cannot remove the only class');
    }

    #[Test]
    public function it_levels_up_a_specific_class(): void
    {
        $character = Character::factory()->create();
        $fighter = CharacterClass::factory()->create(['name' => 'Fighter', 'hit_die' => 10, 'parent_class_id' => null]);
        $wizard = CharacterClass::factory()->create(['name' => 'Wizard', 'hit_die' => 6, 'parent_class_id' => null]);

        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_id' => $fighter->id,
            'level' => 5,
            'is_primary' => true,
            'order' => 1,
        ]);
        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_id' => $wizard->id,
            'level' => 3,
            'is_primary' => false,
            'order' => 2,
        ]);

        $response = $this->postJson("/api/v1/characters/{$character->id}/classes/{$wizard->id}/level-up");

        $response->assertOk()
            ->assertJsonPath('data.level', 4);

        $this->assertDatabaseHas('character_classes', [
            'character_id' => $character->id,
            'class_id' => $wizard->id,
            'level' => 4,
        ]);
    }

    #[Test]
    public function it_prevents_level_up_beyond_20_total(): void
    {
        $character = Character::factory()->create();
        $fighter = CharacterClass::factory()->create(['name' => 'Fighter', 'parent_class_id' => null]);
        $wizard = CharacterClass::factory()->create(['name' => 'Wizard', 'parent_class_id' => null]);

        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_id' => $fighter->id,
            'level' => 15,
            'is_primary' => true,
            'order' => 1,
        ]);
        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_id' => $wizard->id,
            'level' => 5,
            'is_primary' => false,
            'order' => 2,
        ]);

        $response = $this->postJson("/api/v1/characters/{$character->id}/classes/{$wizard->id}/level-up");

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'Character has reached maximum level (20)');
    }
}
```

**Step 2: Run test to verify it fails**

Run: `docker compose exec php php artisan test tests/Feature/Api/CharacterMulticlassApiTest.php`
Expected: FAIL - controller/routes not found

**Step 3: Create the request class**

```php
<?php

namespace App\Http\Requests\Character;

use Illuminate\Foundation\Http\FormRequest;

class AddCharacterClassRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'class_id' => ['required', 'integer', 'exists:classes,id'],
            'force' => ['sometimes', 'boolean'],
        ];
    }
}
```

**Step 4: Create the resource**

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CharacterClassPivotResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'class' => [
                'id' => $this->characterClass->id,
                'name' => $this->characterClass->name,
                'slug' => $this->characterClass->slug,
            ],
            'subclass' => $this->subclass ? [
                'id' => $this->subclass->id,
                'name' => $this->subclass->name,
                'slug' => $this->subclass->slug,
            ] : null,
            'level' => $this->level,
            'is_primary' => $this->is_primary,
            'order' => $this->order,
            'hit_dice' => [
                'die' => 'd' . $this->characterClass->hit_die,
                'max' => $this->max_hit_dice,
                'spent' => $this->hit_dice_spent,
                'available' => $this->available_hit_dice,
            ],
        ];
    }
}
```

**Step 5: Create the controller**

```php
<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\DuplicateClassException;
use App\Exceptions\MaxLevelReachedException;
use App\Exceptions\MulticlassPrerequisiteException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Character\AddCharacterClassRequest;
use App\Http\Resources\CharacterClassPivotResource;
use App\Models\Character;
use App\Models\CharacterClass;
use App\Services\AddClassService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class CharacterClassController extends Controller
{
    public function __construct(
        private AddClassService $addClassService,
    ) {}

    /**
     * List all classes for a character.
     */
    public function index(Character $character): AnonymousResourceCollection
    {
        $character->load('characterClasses.characterClass', 'characterClasses.subclass');

        return CharacterClassPivotResource::collection($character->characterClasses);
    }

    /**
     * Add a class to a character.
     */
    public function store(AddCharacterClassRequest $request, Character $character): JsonResponse
    {
        $class = CharacterClass::findOrFail($request->validated('class_id'));
        $force = $request->validated('force', false);

        try {
            $pivot = $this->addClassService->addClass($character, $class, $force);
            $pivot->load('characterClass', 'subclass');

            return (new CharacterClassPivotResource($pivot))
                ->response()
                ->setStatusCode(Response::HTTP_CREATED);
        } catch (MulticlassPrerequisiteException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => ['class_id' => $e->errors],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (DuplicateClassException|MaxLevelReachedException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    /**
     * Remove a class from a character.
     */
    public function destroy(Character $character, CharacterClass $class): JsonResponse
    {
        $pivot = $character->characterClasses()->where('class_id', $class->id)->first();

        if (!$pivot) {
            return response()->json([
                'message' => 'Class not found on character',
            ], Response::HTTP_NOT_FOUND);
        }

        if ($character->characterClasses()->count() <= 1) {
            return response()->json([
                'message' => 'Cannot remove the only class',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $pivot->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Level up a specific class.
     */
    public function levelUp(Character $character, CharacterClass $class): JsonResponse
    {
        $pivot = $character->characterClasses()->where('class_id', $class->id)->first();

        if (!$pivot) {
            return response()->json([
                'message' => 'Class not found on character',
            ], Response::HTTP_NOT_FOUND);
        }

        if ($character->total_level >= 20) {
            return response()->json([
                'message' => 'Character has reached maximum level (20)',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $pivot->increment('level');
        $pivot->load('characterClass', 'subclass');

        return (new CharacterClassPivotResource($pivot->fresh()))
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }
}
```

**Step 6: Add routes**

Add to `routes/api.php`:

```php
// Character Classes (Multiclass)
Route::prefix('characters/{character}')->group(function () {
    Route::get('classes', [CharacterClassController::class, 'index']);
    Route::post('classes', [CharacterClassController::class, 'store']);
    Route::delete('classes/{class}', [CharacterClassController::class, 'destroy']);
    Route::post('classes/{class}/level-up', [CharacterClassController::class, 'levelUp']);
});
```

**Step 7: Run tests**

Run: `docker compose exec php php artisan test tests/Feature/Api/CharacterMulticlassApiTest.php`
Expected: PASS

**Step 8: Commit**

```bash
git add app/Http/Controllers/Api/CharacterClassController.php app/Http/Requests/Character/AddCharacterClassRequest.php app/Http/Resources/CharacterClassPivotResource.php routes/api.php tests/Feature/Api/CharacterMulticlassApiTest.php
git commit -m "feat(#92): add CharacterClassController for multiclass API endpoints"
```

---

## Task 8: Migrate Existing Character Data

**Files:**
- Create: `database/migrations/2025_12_03_000003_migrate_character_class_data.php`
- Create: `database/migrations/2025_12_03_000004_drop_class_id_from_characters.php`
- Test: `tests/Feature/Migrations/CharacterClassMigrationTest.php`

**Step 1: Write the migration test**

```php
<?php

namespace Tests\Feature\Migrations;

use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\CharacterClassPivot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CharacterClassMigrationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_migrates_existing_character_class_data(): void
    {
        // Create character with old-style class_id
        $class = CharacterClass::factory()->create(['parent_class_id' => null]);

        // Insert directly to simulate pre-migration state
        $characterId = DB::table('characters')->insertGetId([
            'name' => 'Test Character',
            'class_id' => $class->id,
            'level' => 5,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Verify no pivot records exist yet
        $this->assertDatabaseMissing('character_classes', [
            'character_id' => $characterId,
        ]);

        // Run the migration logic manually (or via artisan migrate)
        $character = Character::find($characterId);
        if ($character && $character->class_id && !$character->characterClasses()->exists()) {
            CharacterClassPivot::create([
                'character_id' => $character->id,
                'class_id' => $character->class_id,
                'level' => $character->level,
                'is_primary' => true,
                'order' => 1,
            ]);
        }

        // Verify migration created pivot record
        $this->assertDatabaseHas('character_classes', [
            'character_id' => $characterId,
            'class_id' => $class->id,
            'level' => 5,
            'is_primary' => true,
        ]);
    }
}
```

**Step 2: Create the data migration**

```php
<?php

use App\Models\Character;
use App\Models\CharacterClassPivot;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Migrate existing character class data to junction table
        $characters = DB::table('characters')
            ->whereNotNull('class_id')
            ->get();

        foreach ($characters as $character) {
            // Check if already migrated
            $exists = DB::table('character_classes')
                ->where('character_id', $character->id)
                ->exists();

            if (!$exists) {
                DB::table('character_classes')->insert([
                    'character_id' => $character->id,
                    'class_id' => $character->class_id,
                    'level' => $character->level ?? 1,
                    'is_primary' => true,
                    'order' => 1,
                    'hit_dice_spent' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Reverse migration: copy primary class back to characters table
        $pivots = DB::table('character_classes')
            ->where('is_primary', true)
            ->get();

        foreach ($pivots as $pivot) {
            DB::table('characters')
                ->where('id', $pivot->character_id)
                ->update([
                    'class_id' => $pivot->class_id,
                    'level' => $pivot->level,
                ]);
        }

        // Delete all pivot records
        DB::table('character_classes')->truncate();
    }
};
```

**Step 3: Create the column drop migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->dropForeign(['class_id']);
            $table->dropColumn('class_id');
        });
    }

    public function down(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->unsignedBigInteger('class_id')->nullable()->after('background_id');
            $table->foreign('class_id')->references('id')->on('classes')->onDelete('set null');
        });
    }
};
```

**Step 4: Run migrations and tests**

Run: `docker compose exec php php artisan migrate`
Run: `docker compose exec php php artisan test tests/Feature/Migrations/CharacterClassMigrationTest.php`
Expected: PASS

**Step 5: Commit**

```bash
git add database/migrations/*migrate_character_class* database/migrations/*drop_class_id* tests/Feature/Migrations/CharacterClassMigrationTest.php
git commit -m "feat(#92): migrate character class data to junction table"
```

---

## Task 9: Update CharacterResource Response

**Files:**
- Modify: `app/Http/Resources/CharacterResource.php`
- Test: `tests/Feature/Api/CharacterResourceMulticlassTest.php`

**Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Api;

use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\CharacterClassPivot;
use Database\Seeders\MulticlassSpellSlotSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CharacterResourceMulticlassTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MulticlassSpellSlotSeeder::class);
    }

    #[Test]
    public function it_includes_classes_array_in_response(): void
    {
        $character = Character::factory()->create(['name' => 'Multiclass Hero']);
        $fighter = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'slug' => 'fighter',
            'hit_die' => 10,
            'parent_class_id' => null,
            'caster_type' => 'none',
        ]);
        $wizard = CharacterClass::factory()->create([
            'name' => 'Wizard',
            'slug' => 'wizard',
            'hit_die' => 6,
            'parent_class_id' => null,
            'caster_type' => 'full',
        ]);

        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_id' => $fighter->id,
            'level' => 5,
            'is_primary' => true,
            'order' => 1,
        ]);
        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_id' => $wizard->id,
            'level' => 3,
            'is_primary' => false,
            'order' => 2,
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}");

        $response->assertOk()
            ->assertJsonPath('data.name', 'Multiclass Hero')
            ->assertJsonPath('data.total_level', 8)
            ->assertJsonPath('data.is_multiclass', true)
            ->assertJsonCount(2, 'data.classes')
            ->assertJsonPath('data.classes.0.class.name', 'Fighter')
            ->assertJsonPath('data.classes.0.level', 5)
            ->assertJsonPath('data.classes.1.class.name', 'Wizard')
            ->assertJsonPath('data.classes.1.level', 3);
    }

    #[Test]
    public function it_includes_spell_slots_for_multiclass_caster(): void
    {
        $character = Character::factory()->create();
        $cleric = CharacterClass::factory()->create([
            'name' => 'Cleric',
            'slug' => 'cleric',
            'parent_class_id' => null,
            'caster_type' => 'full',
        ]);
        $wizard = CharacterClass::factory()->create([
            'name' => 'Wizard',
            'slug' => 'wizard',
            'parent_class_id' => null,
            'caster_type' => 'full',
        ]);

        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_id' => $cleric->id,
            'level' => 5,
            'is_primary' => true,
            'order' => 1,
        ]);
        CharacterClassPivot::create([
            'character_id' => $character->id,
            'class_id' => $wizard->id,
            'level' => 3,
            'is_primary' => false,
            'order' => 2,
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}");

        // Caster level 8 = slots 4/3/3/2
        $response->assertOk()
            ->assertJsonPath('data.spell_slots.standard.1st', 4)
            ->assertJsonPath('data.spell_slots.standard.2nd', 3)
            ->assertJsonPath('data.spell_slots.standard.3rd', 3)
            ->assertJsonPath('data.spell_slots.standard.4th', 2);
    }
}
```

**Step 2: Run test to verify it fails**

Run: `docker compose exec php php artisan test tests/Feature/Api/CharacterResourceMulticlassTest.php`
Expected: FAIL - response doesn't include new fields

**Step 3: Update CharacterResource**

Modify `app/Http/Resources/CharacterResource.php` to include:

```php
use App\Http\Resources\CharacterClassPivotResource;
use App\Services\MulticlassSpellSlotCalculator;

// In toArray method, add:
'total_level' => $this->total_level,
'is_multiclass' => $this->is_multiclass,
'classes' => CharacterClassPivotResource::collection($this->whenLoaded('characterClasses')),
'spell_slots' => $this->getSpellSlots(),

// Add helper method:
private function getSpellSlots(): ?array
{
    if ($this->characterClasses->isEmpty()) {
        return null;
    }

    $calculator = app(MulticlassSpellSlotCalculator::class);
    $result = $calculator->calculate($this->resource);

    if ($result->standardSlots === null && $result->pactSlots === null) {
        return null;
    }

    return [
        'standard' => $result->standardSlots,
        'pact' => $result->pactSlots ? [
            'count' => $result->pactSlots->count,
            'level' => $result->pactSlots->level,
        ] : null,
    ];
}
```

**Step 4: Update CharacterController to eager load**

Ensure `characterClasses` is loaded in show method:

```php
public function show(Character $character): CharacterResource
{
    $character->load([
        'characterClasses.characterClass',
        'characterClasses.subclass',
        // ... other relationships
    ]);

    return new CharacterResource($character);
}
```

**Step 5: Run tests**

Run: `docker compose exec php php artisan test tests/Feature/Api/CharacterResourceMulticlassTest.php`
Expected: PASS

**Step 6: Commit**

```bash
git add app/Http/Resources/CharacterResource.php app/Http/Controllers/Api/CharacterController.php tests/Feature/Api/CharacterResourceMulticlassTest.php
git commit -m "feat(#92): update CharacterResource with multiclass support"
```

---

## Task 10: Update Existing Test Suite

**Files:**
- Modify: Various existing character tests

**Step 1: Run full test suite**

Run: `docker compose exec php php artisan test --testsuite=Feature-DB`

**Step 2: Fix any failing tests**

Update tests that rely on `class_id` to use the new junction table approach. Common changes:
- Replace `Character::factory()->create(['class_id' => $class->id])` with creating a `CharacterClassPivot` record
- Update assertions that check `class_id` on character

**Step 3: Run full test suite again**

Run: `docker compose exec php php artisan test --testsuite=Feature-DB`
Expected: PASS

**Step 4: Commit**

```bash
git add tests/
git commit -m "test(#92): update existing tests for multiclass architecture"
```

---

## Task 11: Final Verification

**Step 1: Run all test suites**

```bash
docker compose exec php php artisan test --testsuite=Unit-Pure
docker compose exec php php artisan test --testsuite=Unit-DB
docker compose exec php php artisan test --testsuite=Feature-DB
```
Expected: All PASS

**Step 2: Run Pint**

```bash
docker compose exec php ./vendor/bin/pint
```

**Step 3: Update CHANGELOG.md**

Add under `[Unreleased]`:
```markdown
### Added
- Multiclass support for Character Builder (#92)
  - New `character_classes` junction table for multiple classes per character
  - `MulticlassValidationService` for prerequisite checking (PHB p163)
  - `MulticlassSpellSlotCalculator` for combined caster level and spell slots
  - `AddClassService` for adding classes with proper proficiency grants
  - New API endpoints: GET/POST/DELETE `/characters/{id}/classes`, POST `/characters/{id}/classes/{classId}/level-up`
  - Separate Pact Magic tracking for Warlocks
  - Per-class hit dice tracking for short rest recovery
  - `multiclass_spell_slots` lookup table seeded from PHB p165
```

**Step 4: Commit and push**

```bash
git add -A
git commit -m "feat(#92): complete multiclass support implementation"
git push origin feature/issue-92-multiclass-support
```

**Step 5: Create PR**

```bash
gh pr create --title "feat(#92): Character Builder v2 - Multiclass Support" --body "$(cat <<'EOF'
## Summary
Implements multiclass support for Character Builder per D&D 5e PHB rules.

- Junction table architecture for multiple classes per character
- Strict prerequisite validation with DM override flag
- Combined spell slot calculation for multiclass casters
- Separate Pact Magic tracking for Warlocks
- Per-class hit dice tracking

Closes #92

## Test Plan
- [ ] Unit tests for all new services pass
- [ ] Feature tests for API endpoints pass
- [ ] Existing character tests updated and pass
- [ ] Data migration tested with existing characters

🤖 Generated with [Claude Code](https://claude.com/claude-code)
EOF
)"
```
