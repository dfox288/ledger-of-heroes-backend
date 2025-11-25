# CHARACTER-BUILDER-ANALYSIS.md - Corrections & Additions

**Date:** 2025-11-25
**Audit Status:** ✅ VERIFIED
**Action:** Apply these corrections to CHARACTER-BUILDER-ANALYSIS.md

---

## Critical Corrections

### 1. Table Name Correction (Lines 50-69, 191-210)

**REPLACE** all references to `modifiers` table:
```php
// ❌ INCORRECT
$fighter->modifiers()
    ->where('modifier_category', 'ability_score')
```

**WITH:**
```php
// ✅ CORRECT
$fighter->modifiers()  // Model relationship correct
    ->where('modifier_category', 'ability_score')

// Note: Physical table is 'entity_modifiers' but Eloquent relationship uses $table = 'entity_modifiers'
// See: app/Models/Modifier.php line 10
```

**Schema Documentation Fix (Line 50-69):**

**REPLACE:**
```markdown
#### ASI Tracking (COMPLETE - No Work Needed!)
```php
// Already exists in modifiers table:
```

**WITH:**
```markdown
#### ASI Tracking (COMPLETE - No Work Needed!) ✅ VERIFIED

**Database Table:** `entity_modifiers` (polymorphic)
**Verification:** Run `php docs/verify-asi-data.php` to audit ASI data

```php
// Already exists in entity_modifiers table:
// Verified on 2025-11-25: 14/16 base classes have ASI data
// Total: 83 ASI records across all classes
```

---

### 2. Add ASI Verification Data (Insert after line 69)

**ADD NEW SECTION:**

```markdown
**Verified ASI Levels (2025-11-25):**

| Class | ASI Count | Levels |
|-------|-----------|--------|
| Fighter | 7 | 4, 6, 8, 12, 14, 16, 19 |
| Barbarian | 7 | 4, 8, 12, 16, 19, 20, 20 |
| Rogue | 7 | 4, 8, 10, 12, 16, 19, 19 |
| Druid | 7 | 4, 4, 8, 8, 12, 16, 19 |
| Expert Sidekick | 6 | 4, 8, 10, 12, 16, 19 |
| Monk | 6 | 4, 4, 8, 12, 16, 19 |
| Ranger | 6 | 4, 8, 8, 12, 16, 19 |
| Warlock | 6 | 4, 8, 12, 12, 16, 19 |
| Warrior Sidekick | 6 | 4, 8, 12, 14, 16, 19 |
| Artificer | 5 | 4, 8, 12, 16, 19 |
| Bard | 5 | 4, 8, 12, 16, 19 |
| Sorcerer | 5 | 4, 8, 12, 16, 19 |
| Spellcaster Sidekick | 5 | 4, 8, 12, 16, 18 |
| Wizard | 5 | 4, 8, 12, 16, 19 |
| **Cleric** | ⚠️ 0 | **No ASI data - investigate** |
| **Paladin** | ⚠️ 0 | **No ASI data - investigate** |

**Data Structure (Confirmed):**
- `reference_type` = `'App\Models\CharacterClass'`
- `modifier_category` = `'ability_score'`
- `level` = ASI level (4, 6, 8, etc.)
- `value` = `'+2'` (total ability points to distribute)
- `ability_score_id` = `NULL` (player chooses which abilities)
- `is_choice` = `true` (indicates player choice required)

**Storage Example:**
```php
$fighter = CharacterClass::where('slug', 'fighter')->first();
$asiLevels = $fighter->modifiers()
    ->where('modifier_category', 'ability_score')
    ->orderBy('level')
    ->get();

// Result: 7 records at levels [4, 6, 8, 12, 14, 16, 19]
// Each with value='+2', ability_score_id=NULL, is_choice=true
```

**Known Issues:**
- Cleric and Paladin classes missing ASI data (0 records found)
- Some classes have duplicate level entries (Barbarian: 20, 20; Druid: 4, 4, 8, 8; Rogue: 19, 19)
- May need investigation/cleanup before character builder implementation
```

---

### 3. Add Authentication & Authorization Section (Insert before line 783)

**ADD NEW SECTION:**

```markdown
---

## Authentication & Authorization

### User Ownership Model

**All character data MUST be scoped to authenticated users:**

```php
// characters table
$table->unsignedBigInteger('user_id')->nullable(); // FK to users (owner)
$table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
$table->index('user_id');
```

**Controller Pattern:**
```php
public function index(Request $request): JsonResponse
{
    // ALWAYS scope by authenticated user
    $characters = Character::where('user_id', auth()->id())
        ->with(['race', 'classes', 'background'])
        ->paginate(20);

    return CharacterResource::collection($characters);
}

public function show(Request $request, int $id): JsonResponse
{
    // Find or fail with ownership check
    $character = Character::where('user_id', auth()->id())
        ->findOrFail($id);

    return new CharacterResource($character);
}
```

### Authorization Policies

**Create `CharacterPolicy`:**

```php
namespace App\Policies;

use App\Models\Character;
use App\Models\User;

class CharacterPolicy
{
    /**
     * Determine if user can view the character
     */
    public function view(User $user, Character $character): bool
    {
        // Owner can always view
        if ($character->user_id === $user->id) {
            return true;
        }

        // Check if character is shared (implement sharing tokens later)
        return $character->isSharedWith($user);
    }

    /**
     * Determine if user can update the character
     */
    public function update(User $user, Character $character): bool
    {
        return $character->user_id === $user->id;
    }

    /**
     * Determine if user can delete the character
     */
    public function delete(User $user, Character $character): bool
    {
        return $character->user_id === $user->id;
    }

    /**
     * Determine if user can level up the character
     */
    public function levelUp(User $user, Character $character): bool
    {
        return $character->user_id === $user->id;
    }
}
```

**Register in `AuthServiceProvider`:**
```php
protected $policies = [
    Character::class => CharacterPolicy::class,
];
```

**Use in Controllers:**
```php
public function update(CharacterUpdateRequest $request, Character $character)
{
    $this->authorize('update', $character);

    // Update logic...
}
```

### API Authentication

**Laravel Sanctum (Recommended):**

```php
// config/sanctum.php
'expiration' => 60 * 24 * 7, // 7 days

// routes/api.php
Route::middleware('auth:sanctum')->prefix('v1')->group(function () {
    Route::apiResource('characters', CharacterController::class);
    Route::post('characters/{character}/level-up', [CharacterController::class, 'levelUp']);
    // ... other character routes
});
```

**Login Endpoint:**
```php
POST /api/v1/auth/login
{
    "email": "user@example.com",
    "password": "password"
}

Response:
{
    "token": "1|abc123...",
    "user": { ... }
}
```

**API Usage:**
```bash
curl -H "Authorization: Bearer 1|abc123..." \
     https://api.example.com/api/v1/characters
```

### Character Sharing (Phase 8)

**Share Tokens Table:**
```sql
CREATE TABLE character_share_tokens (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    character_id BIGINT NOT NULL,
    token VARCHAR(64) UNIQUE NOT NULL,
    is_read_only BOOLEAN DEFAULT TRUE,
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP,

    FOREIGN KEY (character_id) REFERENCES characters(id) ON DELETE CASCADE,
    INDEX idx_token (token),
    INDEX idx_character_id (character_id)
);
```

**Generate Share Link:**
```php
POST /api/v1/characters/{id}/share
{
    "read_only": true,
    "expires_in_days": 30
}

Response:
{
    "share_url": "https://app.example.com/shared/abc123def456",
    "expires_at": "2025-12-25T00:00:00Z"
}
```

---
```

---

### 4. Fix `classes` Table Migration Documentation (Line 310)

**REPLACE:**
```sql
ALTER TABLE classes ADD COLUMN subclass_selection_level TINYINT AFTER hit_die;
```

**WITH:**
```sql
-- ✅ VERIFIED: classes table has 'slug' column (added in later migration)
-- Need to verify if subclass_selection_level exists or needs migration

-- Check current schema:
-- docker compose exec php php artisan tinker
-- >>> Schema::getColumnListing('classes')

ALTER TABLE classes ADD COLUMN subclass_selection_level TINYINT AFTER hit_die
    COMMENT 'Level when subclass is selected (1-3 typically)';
```

---

### 5. Add Testing Strategy Section (Insert after line 1283)

**ADD NEW SECTION:**

```markdown
---

## Testing Strategy

### Test Coverage Goals

**Minimum Coverage per Phase:**
- Phase 1 (CRUD): 90%+ coverage
- Phase 2 (Leveling): 95%+ coverage (critical business logic)
- Phase 3 (Spells): 90%+ coverage
- Phase 4 (Inventory): 85%+ coverage
- Phase 5 (Feats/ASI): 95%+ coverage (complex validation)
- Phase 6 (Multiclass): 95%+ coverage (edge cases)
- Phase 7 (Combat): 85%+ coverage
- Phase 8 (Export): 80%+ coverage

### Factory Patterns

**Character Factory:**
```php
namespace Database\Factories;

class CharacterFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => $this->faker->name(),
            'race_id' => Race::factory(),
            'background_id' => Background::factory(),
            'level' => 1,
            'experience_points' => 0,
            'current_hp' => 10,
            'max_hp' => 10,
            'temp_hp' => 0,
            'armor_class' => 10,
            'speed' => 30,
            'proficiency_bonus' => 2,
        ];
    }

    /**
     * Character with specific class
     */
    public function withClass(CharacterClass $class, int $level = 1): static
    {
        return $this->has(
            CharacterClassPivot::factory()
                ->state(['class_id' => $class->id, 'class_level' => $level]),
            'classes'
        );
    }

    /**
     * Multiclass character
     */
    public function multiclass(array $classLevels): static
    {
        return $this->state(function (array $attributes) use ($classLevels) {
            return [
                'level' => array_sum($classLevels),
            ];
        })->afterCreating(function (Character $character) use ($classLevels) {
            foreach ($classLevels as $classId => $level) {
                CharacterClassPivot::factory()->create([
                    'character_id' => $character->id,
                    'class_id' => $classId,
                    'class_level' => $level,
                ]);
            }
        });
    }

    /**
     * Level 5 Fighter
     */
    public function level5Fighter(): static
    {
        $fighter = CharacterClass::where('slug', 'fighter')->first();
        return $this->withClass($fighter, 5)
            ->state(['level' => 5, 'proficiency_bonus' => 3]);
    }
}
```

### Feature Test Examples

**Character CRUD Test:**
```php
namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\Character;
use App\Models\User;

class CharacterControllerTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function user_can_create_character_with_standard_array()
    {
        $user = User::factory()->create();
        $race = Race::factory()->create();
        $class = CharacterClass::factory()->create();
        $background = Background::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/characters', [
                'name' => 'Thorin Ironforge',
                'race_id' => $race->id,
                'class_id' => $class->id,
                'background_id' => $background->id,
                'ability_scores' => [
                    'generation_method' => 'standard_array',
                    'assignments' => [
                        'STR' => 15, 'DEX' => 14, 'CON' => 13,
                        'INT' => 12, 'WIS' => 10, 'CHA' => 8,
                    ],
                ],
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['data' => ['id', 'name', 'race', 'class']]);

        $this->assertDatabaseHas('characters', [
            'name' => 'Thorin Ironforge',
            'user_id' => $user->id,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function user_cannot_view_another_users_character()
    {
        $owner = User::factory()->create();
        $character = Character::factory()->for($owner, 'user')->create();

        $otherUser = User::factory()->create();

        $response = $this->actingAs($otherUser, 'sanctum')
            ->getJson("/api/v1/characters/{$character->id}");

        $response->assertStatus(404); // Policy prevents access
    }
}
```

**Level Up Test:**
```php
#[\PHPUnit\Framework\Attributes\Test]
public function fighter_gains_asi_at_level_4()
{
    $user = User::factory()->create();
    $fighter = CharacterClass::where('slug', 'fighter')->first();
    $character = Character::factory()
        ->for($user, 'user')
        ->withClass($fighter, 3)
        ->create(['level' => 3]);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/characters/{$character->id}/level-up", [
            'class_id' => $fighter->id,
            'hp_roll' => 8,
            'asi_or_feat' => 'asi',
            'asi_choices' => ['STR' => 1, 'DEX' => 1],
        ]);

    $response->assertStatus(200);

    $this->assertEquals(4, $character->fresh()->level);

    // Verify ASI was applied
    $strScore = $character->abilityScores()
        ->where('ability_score_id', AbilityScore::where('code', 'STR')->first()->id)
        ->first();
    $this->assertEquals(1, $strScore->asi_bonus);
}
```

### Unit Test Examples

**Ability Score Service Test:**
```php
namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\AbilityScoreService;

class AbilityScoreServiceTest extends TestCase
{
    private AbilityScoreService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AbilityScoreService();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function calculates_modifier_correctly()
    {
        $this->assertEquals(-5, $this->service->calculateModifier(1));
        $this->assertEquals(0, $this->service->calculateModifier(10));
        $this->assertEquals(0, $this->service->calculateModifier(11));
        $this->assertEquals(+3, $this->service->calculateModifier(16));
        $this->assertEquals(+5, $this->service->calculateModifier(20));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function validates_point_buy_correctly()
    {
        // Valid: 27 points, scores 8-15
        $valid = ['STR' => 15, 'DEX' => 14, 'CON' => 13, 'INT' => 12, 'WIS' => 10, 'CHA' => 8];
        $this->assertTrue($this->service->validatePointBuy($valid));

        // Invalid: 28 points (too many)
        $invalid = ['STR' => 15, 'DEX' => 15, 'CON' => 13, 'INT' => 12, 'WIS' => 10, 'CHA' => 8];
        $this->assertFalse($this->service->validatePointBuy($invalid));

        // Invalid: score above 15
        $invalid2 = ['STR' => 16, 'DEX' => 14, 'CON' => 13, 'INT' => 8, 'WIS' => 8, 'CHA' => 8];
        $this->assertFalse($this->service->validatePointBuy($invalid2));
    }
}
```

### Database Seeding for Tests

**CharacterSeeder (for manual testing):**
```php
namespace Database\Seeders;

class CharacterSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::first() ?? User::factory()->create([
            'email' => 'test@example.com',
        ]);

        $fighter = CharacterClass::where('slug', 'fighter')->first();
        $dwarf = Race::where('slug', 'mountain-dwarf')->first();
        $soldier = Background::where('slug', 'soldier')->first();

        // Level 5 Fighter
        $thorin = Character::factory()
            ->for($user, 'user')
            ->create([
                'name' => 'Thorin Ironforge',
                'race_id' => $dwarf->id,
                'background_id' => $soldier->id,
                'level' => 5,
            ]);

        // Add Fighter class
        CharacterClassPivot::create([
            'character_id' => $thorin->id,
            'class_id' => $fighter->id,
            'class_level' => 5,
            'hit_points_rolled' => json_encode([10, 7, 8, 6, 9]),
        ]);

        // Add ability scores
        $abilities = [
            'STR' => 17, 'DEX' => 12, 'CON' => 15,
            'INT' => 8, 'WIS' => 10, 'CHA' => 13,
        ];

        foreach ($abilities as $code => $score) {
            $ability = AbilityScore::where('code', $code)->first();
            CharacterAbilityScore::create([
                'character_id' => $thorin->id,
                'ability_score_id' => $ability->id,
                'base_score' => $score - 2, // Before racial bonus
                'racial_bonus' => 2,
            ]);
        }
    }
}
```

### Test Execution

```bash
# Run all character builder tests
docker compose exec php php artisan test --filter=Character

# Run specific test suite
docker compose exec php php artisan test tests/Feature/Api/CharacterControllerTest.php

# Run with coverage
docker compose exec php php artisan test --coverage --min=90

# Watch mode for TDD
docker compose exec php php artisan test --watch
```

---
```

---

### 6. Update Effort Estimates (Lines 1169-1210)

**REPLACE:**
```markdown
### MVP (Phases 1-4): 50-66 hours

### Full Character Builder (Phases 1-7): 76-102 hours

### Complete System (Phases 1-8): 84-114 hours

### Revised Estimate (With ASI Already Done)
- MVP: **46-60 hours**
- Full: **72-96 hours**
- Complete: **79-108 hours**
```

**WITH:**
```markdown
### MVP (Phases 1-4): 50-66 hours
**Revised with Auth:** 58-76 hours (+8-10h for auth/policies/testing)

### Full Character Builder (Phases 1-7): 76-102 hours
**Revised with Buffers:** 86-116 hours (+10-14h for edge cases)

### Complete System (Phases 1-8): 84-114 hours
**Revised with Auth & Testing:** 94-126 hours (+10-12h comprehensive testing)

### Revised Estimate (With ASI Already Done, Auth Added)

**ASI tracking saves:** 4-6 hours ✅
**Auth/testing adds:** 10-14 hours ⚠️
**Net adjustment:** +6-8 hours

**Final Estimates:**
- **MVP:** 58-76 hours (1.5-2 months @ 10h/week)
- **Full:** 86-116 hours (2-3 months @ 10h/week)
- **Complete:** 94-126 hours (2.5-3.5 months @ 10h/week)

**Phase Breakdown with Auth:**

| Phase | Original | +Auth | +Testing | Final Estimate |
|-------|----------|-------|----------|----------------|
| Phase 1 (CRUD) | 12-16h | +2h | +2h | 16-20h |
| Phase 2 (Leveling) | 14-18h | - | +4h | 18-22h |
| Phase 3 (Spells) | 12-16h | - | +4h | 16-20h |
| Phase 4 (Inventory) | 12-16h | - | +2h | 14-18h |
| Phase 5 (Feats/ASI) | 8-12h | - | +2h | 10-14h |
| Phase 6 (Multiclass) | 12-16h | - | +6h | 18-22h |
| Phase 7 (Combat) | 6-8h | - | +2h | 8-10h |
| Phase 8 (Export) | 8-12h | - | - | 8-12h |
```

---

### 7. Add Performance Considerations Section (Insert before line 1433)

**ADD NEW SECTION:**

```markdown
---

## Performance Considerations

### N+1 Query Prevention

**ALWAYS eager load relationships:**

```php
// ❌ BAD: N+1 queries
$characters = Character::where('user_id', auth()->id())->get();
foreach ($characters as $character) {
    echo $character->race->name; // +1 query per character
}

// ✅ GOOD: Single query
$characters = Character::where('user_id', auth()->id())
    ->with(['race', 'background', 'classes.class'])
    ->get();
```

**Character Sheet Endpoint Optimization:**
```php
public function show(Character $character): JsonResponse
{
    $character->load([
        'race.modifiers.abilityScore',
        'background.proficiencies',
        'classes.class.features',
        'abilityScores.ability',
        'spells.spell.school',
        'items.item.itemType',
        'feats.feat.prerequisites',
        'proficiencies',
    ]);

    return new CharacterSheetResource($character);
}
```

### Caching Strategy

**Cache calculated stats (AC, spell save DC, initiative):**

```php
namespace App\Models;

class Character extends Model
{
    /**
     * Cache armor class calculation (expensive)
     */
    public function getArmorClassAttribute(): int
    {
        return Cache::remember(
            "character.{$this->id}.ac",
            now()->addHours(1),
            fn() => app(ArmorClassService::class)->calculateAC($this)
        );
    }

    /**
     * Invalidate cache on equipment/ability changes
     */
    protected static function boot()
    {
        parent::boot();

        static::updated(function($character) {
            Cache::forget("character.{$character->id}.ac");
            Cache::forget("character.{$character->id}.stats");
        });
    }
}
```

**Cache character sheet JSON:**
```php
public function show(Character $character): JsonResponse
{
    $cacheKey = "character.{$character->id}.sheet.v1";

    $data = Cache::tags(['characters'])->remember(
        $cacheKey,
        now()->addMinutes(10),
        fn() => new CharacterSheetResource($character)
    );

    return response()->json($data);
}

// Invalidate on update
public function update(CharacterUpdateRequest $request, Character $character)
{
    $character->update($request->validated());
    Cache::tags(['characters'])->forget("character.{$character->id}.sheet.v1");
}
```

### Database Indexing

**Essential indexes for character queries:**

```php
// characters table
$table->index('user_id');
$table->index(['user_id', 'level']);
$table->index(['user_id', 'created_at']);

// character_classes
$table->unique(['character_id', 'class_id']);
$table->index('character_id');
$table->index('class_id');

// character_spells
$table->unique(['character_id', 'spell_id']);
$table->index(['character_id', 'is_prepared']);
$table->index(['character_id', 'source_type']);

// character_items
$table->index(['character_id', 'is_equipped']);
$table->index(['character_id', 'is_attuned']);

// character_ability_scores
$table->unique(['character_id', 'ability_score_id']);
$table->index('character_id');
```

### Pagination

**Always paginate list endpoints:**

```php
// ❌ BAD: Load all characters
$characters = Character::where('user_id', auth()->id())->get();

// ✅ GOOD: Paginate
$characters = Character::where('user_id', auth()->id())
    ->with(['race', 'classes.class'])
    ->orderBy('created_at', 'desc')
    ->paginate(20);
```

### Background Jobs for Heavy Operations

**Level up with recalculations:**

```php
namespace App\Jobs;

class RecalculateCharacterStats implements ShouldQueue
{
    public function __construct(
        public Character $character
    ) {}

    public function handle(): void
    {
        // Recalculate max HP (CON modifier may have changed)
        $this->character->max_hp = app(HitPointService::class)
            ->recalculateMaxHP($this->character);

        // Recalculate AC (DEX/items may have changed)
        $this->character->armor_class = app(ArmorClassService::class)
            ->calculateAC($this->character);

        // Recalculate spell save DC
        foreach ($this->character->classes as $charClass) {
            if ($charClass->class->spellcasting_ability_id) {
                $charClass->spell_save_dc = app(SpellcastingService::class)
                    ->calculateSpellSaveDC($this->character, $charClass);
            }
        }

        $this->character->save();

        // Invalidate caches
        Cache::tags(['characters'])->flush();
    }
}

// Dispatch after level up
dispatch(new RecalculateCharacterStats($character));
```

---
```

---

### 8. Fix Multiclass Spell Slot Logic Note (Line 723-725)

**REPLACE:**
```php
// Third casters (Eldritch Knight, Arcane Trickster)
elseif (in_array($class->slug, ['fighter-eldritch-knight', 'rogue-arcane-trickster'])) {
```

**WITH:**
```php
// Third casters (Eldritch Knight, Arcane Trickster)
// Note: These are SUBCLASSES, check parent_class_id or subclass relationship
elseif (
    ($class->parentClass?->slug === 'fighter' && str_contains($class->slug, 'eldritch-knight')) ||
    ($class->parentClass?->slug === 'rogue' && str_contains($class->slug, 'arcane-trickster'))
) {
```

---

### 9. Add Spell Preparation Clarification (Line 883)

**REPLACE:**
```php
"spell_ids": [15, 23, 42, 67, 89, 103, 156, 201]  // Up to (WIS/INT mod + level) spells
```

**WITH:**
```php
"spell_ids": [15, 23, 42, 67, 89, 103, 156, 201]
// Limit varies by class:
// - Cleric: WIS modifier + cleric level
// - Druid: WIS modifier + druid level
// - Wizard: INT modifier + wizard level
// - Paladin: CHA modifier + half paladin level (rounded down)
// Always use CLASS level, not total character level (important for multiclass)
```

---

### 10. Add Data Verification Task to Quick Wins (Line 1213)

**INSERT BEFORE "Task 1: Add Subclass Selection Level":**

```markdown
### Task 0: Verify ASI Data & Investigate Missing Classes (1 hour)

**Run verification script:**
```bash
docker compose exec php php docs/verify-asi-data.php
```

**Expected Output:**
- ✅ Fighter: 7 ASIs at [4, 6, 8, 12, 14, 16, 19]
- ✅ 14/16 base classes have ASI data
- ⚠️ Cleric and Paladin: 0 ASIs (investigate XML/import)

**Investigation Steps:**
1. Check if Cleric/Paladin XML files contain ASI data
2. Verify ClassImporter processes ability_score modifiers
3. Check import logs for errors
4. Re-import if needed: `docker compose exec php php artisan import:classes import-files/class-cleric.xml`

**Test Query:**
```php
docker compose exec php php artisan tinker
>>> CharacterClass::where('slug', 'cleric')->first()
    ->modifiers()->where('modifier_category', 'ability_score')->get()
// Should return 5 records at levels [4, 8, 12, 16, 19]
```

---
```

---

## Summary of Changes

| Section | Type | Priority | Lines Affected |
|---------|------|----------|----------------|
| 1. Table name correction | Fix | Critical | 50-69, 191-210 |
| 2. Add verified ASI data | Addition | High | After 69 |
| 3. Add Auth & Authorization | Addition | Critical | Before 783 |
| 4. Fix classes migration docs | Fix | Medium | 310 |
| 5. Add Testing Strategy | Addition | High | After 1283 |
| 6. Update effort estimates | Update | High | 1169-1210 |
| 7. Add Performance section | Addition | Medium | Before 1433 |
| 8. Fix multiclass subclass logic | Fix | Medium | 723-725 |
| 9. Clarify spell preparation | Fix | Low | 883 |
| 10. Add ASI verification task | Addition | High | Before 1213 |

---

## How to Apply

### Option 1: Manual Edits
1. Open `docs/CHARACTER-BUILDER-ANALYSIS.md`
2. Search for each "REPLACE" section by line number
3. Apply changes manually
4. Save file

### Option 2: Automated (if comfortable with sed/awk)
```bash
# Backup first
cp docs/CHARACTER-BUILDER-ANALYSIS.md docs/CHARACTER-BUILDER-ANALYSIS.md.backup

# Apply corrections (requires manual review after)
# ... scripting complex, manual editing recommended
```

### Option 3: Request Full Rewrite
Ask Claude to generate complete corrected document (large response).

---

**Status:** ✅ Ready to Apply
**Verification Script:** `docs/verify-asi-data.php` (created and tested)
**Audit Date:** 2025-11-25
**Audited By:** Claude Code
