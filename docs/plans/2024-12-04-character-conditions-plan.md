# Character Condition Tracking - Implementation Plan

**Issue:** #117 - Character Builder: Condition Tracking
**Design:** `docs/plans/2024-12-04-character-conditions-design.md`
**Branch:** `feature/issue-117-condition-tracking`

---

## Pre-Flight

- [ ] Confirm Sail running: `docker compose ps`
- [ ] Create branch: `git checkout -b feature/issue-117-condition-tracking`
- [ ] Verify clean state: `git status`

---

## Task 1: Migration & Model

**Goal:** Create `character_conditions` table and `CharacterCondition` model

### 1.1 Create Migration

```bash
sail artisan make:migration create_character_conditions_table
```

**File:** `database/migrations/xxxx_create_character_conditions_table.php`

```php
public function up(): void
{
    Schema::create('character_conditions', function (Blueprint $table) {
        $table->id();
        $table->foreignId('character_id')->constrained()->cascadeOnDelete();
        $table->foreignId('condition_id')->constrained()->cascadeOnDelete();
        $table->unsignedTinyInteger('level')->nullable();
        $table->string('source')->nullable();
        $table->string('duration')->nullable();
        $table->timestamps();

        $table->unique(['character_id', 'condition_id']);
    });
}
```

### 1.2 Create Model

**File:** `app/Models/CharacterCondition.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CharacterCondition extends Model
{
    use HasFactory;

    protected $fillable = [
        'character_id',
        'condition_id',
        'level',
        'source',
        'duration',
    ];

    protected $casts = [
        'level' => 'integer',
    ];

    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }

    public function condition(): BelongsTo
    {
        return $this->belongsTo(Condition::class);
    }
}
```

### 1.3 Create Factory

**File:** `database/factories/CharacterConditionFactory.php`

```php
<?php

namespace Database\Factories;

use App\Models\Character;
use App\Models\Condition;
use Illuminate\Database\Eloquent\Factories\Factory;

class CharacterConditionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'character_id' => Character::factory(),
            'condition_id' => Condition::factory(),
            'level' => null,
            'source' => fake()->optional()->sentence(3),
            'duration' => fake()->optional()->randomElement(['1 minute', '1 hour', 'Until cured', 'Until long rest']),
        ];
    }

    public function exhaustion(int $level = 1): static
    {
        return $this->state(fn () => [
            'condition_id' => Condition::where('slug', 'exhaustion')->first()?->id ?? Condition::factory()->state(['slug' => 'exhaustion', 'name' => 'Exhaustion']),
            'level' => $level,
        ]);
    }
}
```

### 1.4 Add Relationship to Character Model

**File:** `app/Models/Character.php` - Add method:

```php
public function conditions(): HasMany
{
    return $this->hasMany(CharacterCondition::class);
}
```

### 1.5 Run Migration & Verify

```bash
sail artisan migrate
sail artisan migrate:fresh --seed --env=testing
```

**Commit:** `feat(#117): Add character_conditions migration, model, and factory`

---

## Task 2: Model Tests (TDD Red)

**Goal:** Write failing tests for model relationships

**File:** `tests/Feature/Models/CharacterConditionModelTest.php`

```php
<?php

namespace Tests\Feature\Models;

use App\Models\Character;
use App\Models\CharacterCondition;
use App\Models\Condition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CharacterConditionModelTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_belongs_to_a_character(): void
    {
        $characterCondition = CharacterCondition::factory()->create();

        $this->assertInstanceOf(Character::class, $characterCondition->character);
    }

    #[Test]
    public function it_belongs_to_a_condition(): void
    {
        $characterCondition = CharacterCondition::factory()->create();

        $this->assertInstanceOf(Condition::class, $characterCondition->condition);
    }

    #[Test]
    public function it_can_have_a_level_for_exhaustion(): void
    {
        $exhaustion = Condition::factory()->create(['slug' => 'exhaustion', 'name' => 'Exhaustion']);
        $characterCondition = CharacterCondition::factory()->create([
            'condition_id' => $exhaustion->id,
            'level' => 3,
        ]);

        $this->assertEquals(3, $characterCondition->level);
    }

    #[Test]
    public function it_enforces_unique_condition_per_character(): void
    {
        $character = Character::factory()->create();
        $condition = Condition::factory()->create();

        CharacterCondition::factory()->create([
            'character_id' => $character->id,
            'condition_id' => $condition->id,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        CharacterCondition::factory()->create([
            'character_id' => $character->id,
            'condition_id' => $condition->id,
        ]);
    }

    #[Test]
    public function character_has_many_conditions(): void
    {
        $character = Character::factory()->create();
        CharacterCondition::factory()->count(3)->create(['character_id' => $character->id]);

        $this->assertCount(3, $character->conditions);
    }
}
```

**Run:** `sail artisan test --testsuite=Unit-DB --filter=CharacterConditionModelTest`

**Commit:** `test(#117): Add CharacterCondition model tests`

---

## Task 3: Form Request & Resource

**Goal:** Create validation and response formatting

### 3.1 Store Request

**File:** `app/Http/Requests/CharacterCondition/StoreCharacterConditionRequest.php`

```php
<?php

namespace App\Http\Requests\CharacterCondition;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCharacterConditionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'condition_id' => ['required', 'integer', Rule::exists('conditions', 'id')],
            'level' => ['nullable', 'integer', 'min:1', 'max:6'],
            'source' => ['nullable', 'string', 'max:255'],
            'duration' => ['nullable', 'string', 'max:255'],
        ];
    }
}
```

### 3.2 Resource

**File:** `app/Http/Resources/CharacterConditionResource.php`

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CharacterConditionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isExhaustion = $this->condition->slug === 'exhaustion';

        return [
            'id' => $this->id,
            'condition' => [
                'id' => $this->condition->id,
                'name' => $this->condition->name,
                'slug' => $this->condition->slug,
            ],
            'level' => $this->level,
            'source' => $this->source,
            'duration' => $this->duration,
            'is_exhaustion' => $isExhaustion,
            'exhaustion_warning' => $isExhaustion && $this->level === 6
                ? 'Level 6 exhaustion results in death'
                : null,
        ];
    }
}
```

**Commit:** `feat(#117): Add StoreCharacterConditionRequest and CharacterConditionResource`

---

## Task 4: Controller

**Goal:** Implement CRUD endpoints

**File:** `app/Http/Controllers/Api/CharacterConditionController.php`

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CharacterCondition\StoreCharacterConditionRequest;
use App\Http\Resources\CharacterConditionResource;
use App\Models\Character;
use App\Models\CharacterCondition;
use App\Models\Condition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CharacterConditionController extends Controller
{
    /**
     * List all active conditions for a character.
     */
    public function index(Character $character): AnonymousResourceCollection
    {
        $conditions = $character->conditions()->with('condition')->get();

        return CharacterConditionResource::collection($conditions);
    }

    /**
     * Add or update a condition on a character.
     */
    public function store(StoreCharacterConditionRequest $request, Character $character): CharacterConditionResource
    {
        $condition = Condition::findOrFail($request->condition_id);
        $isExhaustion = $condition->slug === 'exhaustion';

        // Determine level
        $level = null;
        if ($isExhaustion) {
            $level = $request->level ?? 1;
        }

        // Upsert - update if exists, create if not
        $characterCondition = CharacterCondition::updateOrCreate(
            [
                'character_id' => $character->id,
                'condition_id' => $condition->id,
            ],
            [
                'level' => $level,
                'source' => $request->source,
                'duration' => $request->duration,
            ]
        );

        $characterCondition->load('condition');

        return new CharacterConditionResource($characterCondition);
    }

    /**
     * Remove a condition from a character.
     */
    public function destroy(Character $character, string $condition): JsonResponse
    {
        // Find by ID or slug
        $conditionModel = is_numeric($condition)
            ? Condition::findOrFail($condition)
            : Condition::where('slug', $condition)->firstOrFail();

        $deleted = $character->conditions()
            ->where('condition_id', $conditionModel->id)
            ->delete();

        if ($deleted === 0) {
            abort(404, 'Character does not have this condition');
        }

        return response()->json(null, 204);
    }
}
```

**Commit:** `feat(#117): Add CharacterConditionController`

---

## Task 5: Routes

**File:** `routes/api.php` - Add inside character routes group:

```php
// Character conditions
Route::get('characters/{character}/conditions', [CharacterConditionController::class, 'index']);
Route::post('characters/{character}/conditions', [CharacterConditionController::class, 'store']);
Route::delete('characters/{character}/conditions/{condition}', [CharacterConditionController::class, 'destroy']);
```

**Commit:** `feat(#117): Add character condition routes`

---

## Task 6: Feature Tests (TDD Red → Green)

**Goal:** Write comprehensive API tests

**File:** `tests/Feature/Api/CharacterConditionApiTest.php`

```php
<?php

namespace Tests\Feature\Api;

use App\Models\Character;
use App\Models\CharacterCondition;
use App\Models\Condition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CharacterConditionApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_empty_array_when_character_has_no_conditions(): void
    {
        $character = Character::factory()->create();

        $response = $this->getJson("/api/v1/characters/{$character->id}/conditions");

        $response->assertOk()
            ->assertJson(['data' => []]);
    }

    #[Test]
    public function it_lists_all_conditions_for_a_character(): void
    {
        $character = Character::factory()->create();
        $conditions = Condition::factory()->count(2)->create();

        foreach ($conditions as $condition) {
            CharacterCondition::factory()->create([
                'character_id' => $character->id,
                'condition_id' => $condition->id,
            ]);
        }

        $response = $this->getJson("/api/v1/characters/{$character->id}/conditions");

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    #[Test]
    public function it_adds_a_condition_to_a_character(): void
    {
        $character = Character::factory()->create();
        $condition = Condition::factory()->create(['name' => 'Poisoned', 'slug' => 'poisoned']);

        $response = $this->postJson("/api/v1/characters/{$character->id}/conditions", [
            'condition_id' => $condition->id,
            'source' => 'Spider bite',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.condition.name', 'Poisoned')
            ->assertJsonPath('data.source', 'Spider bite')
            ->assertJsonPath('data.is_exhaustion', false);

        $this->assertDatabaseHas('character_conditions', [
            'character_id' => $character->id,
            'condition_id' => $condition->id,
            'source' => 'Spider bite',
        ]);
    }

    #[Test]
    public function it_upserts_when_adding_existing_condition(): void
    {
        $character = Character::factory()->create();
        $condition = Condition::factory()->create();

        CharacterCondition::factory()->create([
            'character_id' => $character->id,
            'condition_id' => $condition->id,
            'source' => 'Original source',
        ]);

        $response = $this->postJson("/api/v1/characters/{$character->id}/conditions", [
            'condition_id' => $condition->id,
            'source' => 'Updated source',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.source', 'Updated source');

        $this->assertDatabaseCount('character_conditions', 1);
    }

    #[Test]
    public function it_defaults_exhaustion_level_to_1(): void
    {
        $character = Character::factory()->create();
        $exhaustion = Condition::factory()->create(['name' => 'Exhaustion', 'slug' => 'exhaustion']);

        $response = $this->postJson("/api/v1/characters/{$character->id}/conditions", [
            'condition_id' => $exhaustion->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.level', 1)
            ->assertJsonPath('data.is_exhaustion', true);
    }

    #[Test]
    public function it_validates_exhaustion_level_range(): void
    {
        $character = Character::factory()->create();
        $exhaustion = Condition::factory()->create(['slug' => 'exhaustion']);

        $response = $this->postJson("/api/v1/characters/{$character->id}/conditions", [
            'condition_id' => $exhaustion->id,
            'level' => 7,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('level');
    }

    #[Test]
    public function it_warns_at_exhaustion_level_6(): void
    {
        $character = Character::factory()->create();
        $exhaustion = Condition::factory()->create(['slug' => 'exhaustion']);

        $response = $this->postJson("/api/v1/characters/{$character->id}/conditions", [
            'condition_id' => $exhaustion->id,
            'level' => 6,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.exhaustion_warning', 'Level 6 exhaustion results in death');
    }

    #[Test]
    public function it_ignores_level_for_non_exhaustion_conditions(): void
    {
        $character = Character::factory()->create();
        $poisoned = Condition::factory()->create(['slug' => 'poisoned']);

        $response = $this->postJson("/api/v1/characters/{$character->id}/conditions", [
            'condition_id' => $poisoned->id,
            'level' => 3,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.level', null);
    }

    #[Test]
    public function it_removes_a_condition_by_id(): void
    {
        $character = Character::factory()->create();
        $condition = Condition::factory()->create();

        CharacterCondition::factory()->create([
            'character_id' => $character->id,
            'condition_id' => $condition->id,
        ]);

        $response = $this->deleteJson("/api/v1/characters/{$character->id}/conditions/{$condition->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('character_conditions', [
            'character_id' => $character->id,
            'condition_id' => $condition->id,
        ]);
    }

    #[Test]
    public function it_removes_a_condition_by_slug(): void
    {
        $character = Character::factory()->create();
        $condition = Condition::factory()->create(['slug' => 'poisoned']);

        CharacterCondition::factory()->create([
            'character_id' => $character->id,
            'condition_id' => $condition->id,
        ]);

        $response = $this->deleteJson("/api/v1/characters/{$character->id}/conditions/poisoned");

        $response->assertNoContent();
    }

    #[Test]
    public function it_returns_404_when_removing_condition_character_does_not_have(): void
    {
        $character = Character::factory()->create();
        $condition = Condition::factory()->create();

        $response = $this->deleteJson("/api/v1/characters/{$character->id}/conditions/{$condition->id}");

        $response->assertNotFound();
    }

    #[Test]
    public function it_returns_422_for_invalid_condition_id(): void
    {
        $character = Character::factory()->create();

        $response = $this->postJson("/api/v1/characters/{$character->id}/conditions", [
            'condition_id' => 99999,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('condition_id');
    }
}
```

**Run:** `sail artisan test --testsuite=Feature-DB --filter=CharacterConditionApiTest`

**Commit:** `test(#117): Add CharacterCondition API tests`

---

## Task 7: Update CharacterResource

**Goal:** Include conditions in character response

**File:** `app/Http/Resources/CharacterResource.php` - Add to `toArray()`:

```php
'conditions' => $this->whenLoaded('conditions', function () {
    return $this->conditions->map(fn ($cc) => [
        'id' => $cc->condition->id,
        'name' => $cc->condition->name,
        'slug' => $cc->condition->slug,
        'level' => $cc->level,
        'source' => $cc->source,
    ]);
}),
```

**Update eager loading in CharacterController** where appropriate.

**Commit:** `feat(#117): Include conditions in CharacterResource`

---

## Task 8: Quality Gates

```bash
# Format
sail composer pint

# Static analysis (if configured)
sail composer analyse

# Run all relevant tests
sail artisan test --testsuite=Unit-DB --filter=CharacterCondition
sail artisan test --testsuite=Feature-DB --filter=CharacterCondition
```

**Commit:** `chore(#117): Run quality gates`

---

## Task 9: Documentation & Cleanup

- [ ] Update CHANGELOG.md under [Unreleased]
- [ ] Verify all tests pass
- [ ] Push branch and create PR

```bash
git push -u origin feature/issue-117-condition-tracking
gh pr create --title "feat(#117): Character condition tracking" --body "Closes #117

## Summary
- Add character_conditions table for tracking active conditions
- CRUD endpoints for managing conditions on characters
- Special handling for exhaustion levels (1-6)
- Include conditions in CharacterResource

## Test Plan
- [x] Model tests pass
- [x] API tests pass
- [x] Quality gates pass
"
```

---

## Verification Checklist

- [ ] Migration runs clean
- [ ] All model tests pass
- [ ] All API tests pass
- [ ] Pint formatting clean
- [ ] CharacterResource includes conditions
- [ ] PR created and linked to issue
