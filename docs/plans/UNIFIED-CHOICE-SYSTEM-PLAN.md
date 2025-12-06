# Unified Choice System Implementation Plan

**Epic:** #246
**Branch:** `feature/issue-246-unified-choice-system`
**Environment:** Sail (detected)

## Status

| Phase | Status | Issues |
|-------|--------|--------|
| Phase 1: Core Infrastructure | ✅ Complete | #247 |
| Phase 2: Migrate Existing | ✅ Complete | #249, #250, #257 |
| Phase 3: Add Missing Types | 🔄 In Progress | #259, #251, #252, #253, #260, #261 |

### Completed Handlers
- `ProficiencyChoiceHandler` - skill/tool/weapon/armor choices
- `LanguageChoiceHandler` - language choices from race/background/feat
- `AsiChoiceHandler` - ASI/Feat choices at levels 4,8,12,16,19

### Remaining Handlers
- `OptionalFeatureChoiceHandler` (#259) - invocations, maneuvers, metamagic
- `EquipmentChoiceHandler` (#251) - starting equipment
- `SubclassChoiceHandler` (#252) - subclass selection
- `SpellChoiceHandler` (#253) - cantrips and spells known
- `ExpertiseChoiceHandler` (#260) - Rogue/Bard expertise
- `FightingStyleChoiceHandler` (#261) - Fighter/Ranger/Paladin

---

## Overview

Replace fragmented choice endpoints with a unified system. All character choices (proficiencies, languages, equipment, spells, ASI/feat, subclass, optional features, expertise, fighting styles) flow through a single API pattern.

## New Endpoints

```
GET  /characters/{id}/pending-choices           # All pending choices
GET  /characters/{id}/pending-choices?type=X    # Filter by type
GET  /characters/{id}/pending-choices/{choiceId} # Single choice details
POST /characters/{id}/choices/{choiceId}        # Resolve a choice
DELETE /characters/{id}/choices/{choiceId}      # Undo (if allowed)
```

## Deprecated Endpoints (Keep Working)

```
GET/POST /proficiency-choices    -> Use pending-choices?type=proficiency
GET/POST /language-choices       -> Use pending-choices?type=language
POST /asi-choice                 -> Use choices/{choiceId}
GET/POST /feature-selections     -> Use pending-choices?type=optional_feature
```

---

## Phase 1: Core Infrastructure (#247)

### Task 1.1: Create PendingChoice DTO

**File:** `app/DTOs/PendingChoice.php`

```php
<?php

declare(strict_types=1);

namespace App\DTOs;

final readonly class PendingChoice
{
    /**
     * @param string $id Deterministic choice ID: {type}:{source}:{sourceId}:{level}:{group}
     * @param string $type Choice type: proficiency, language, equipment, spell, asi_or_feat, subclass, optional_feature, expertise, fighting_style
     * @param string|null $subtype Subtype: skill, tool, cantrip, invocation, etc.
     * @param string $source Source: class, race, background, feat
     * @param string $sourceName Human-readable: "Rogue", "High Elf", etc.
     * @param int $levelGranted Character level when choice became available
     * @param bool $required Blocks completion if unresolved
     * @param int $quantity How many selections needed
     * @param int $remaining Quantity minus already selected
     * @param array $selected Already chosen option IDs/slugs
     * @param array|null $options Available options (null if external endpoint)
     * @param string|null $optionsEndpoint URL for dynamic options
     * @param array $metadata Type-specific extra data
     */
    public function __construct(
        public string $id,
        public string $type,
        public ?string $subtype,
        public string $source,
        public string $sourceName,
        public int $levelGranted,
        public bool $required,
        public int $quantity,
        public int $remaining,
        public array $selected,
        public ?array $options,
        public ?string $optionsEndpoint,
        public array $metadata = [],
    ) {}

    public function isComplete(): bool
    {
        return $this->remaining === 0;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'subtype' => $this->subtype,
            'source' => $this->source,
            'source_name' => $this->sourceName,
            'level_granted' => $this->levelGranted,
            'required' => $this->required,
            'quantity' => $this->quantity,
            'remaining' => $this->remaining,
            'selected' => $this->selected,
            'options' => $this->options,
            'options_endpoint' => $this->optionsEndpoint,
            'metadata' => $this->metadata,
        ];
    }
}
```

**Test:** Unit test for `isComplete()` and `toArray()`.

---

### Task 1.2: Create ChoiceTypeHandler Interface

**File:** `app/Services/ChoiceHandlers/ChoiceTypeHandler.php`

```php
<?php

namespace App\Services\ChoiceHandlers;

use App\DTOs\PendingChoice;
use App\Models\Character;
use Illuminate\Support\Collection;

interface ChoiceTypeHandler
{
    /**
     * Get the choice type this handler manages.
     */
    public function getType(): string;

    /**
     * Get all pending choices of this type for a character.
     *
     * @return Collection<int, PendingChoice>
     */
    public function getChoices(Character $character): Collection;

    /**
     * Resolve a choice with the given selection.
     *
     * @param array $selection The user's selection (format varies by type)
     * @throws \App\Exceptions\InvalidChoiceException
     */
    public function resolve(Character $character, PendingChoice $choice, array $selection): void;

    /**
     * Check if a resolved choice can be undone.
     */
    public function canUndo(Character $character, PendingChoice $choice): bool;

    /**
     * Undo a previously resolved choice.
     *
     * @throws \App\Exceptions\ChoiceNotUndoableException
     */
    public function undo(Character $character, PendingChoice $choice): void;
}
```

---

### Task 1.3: Create Exception Classes

**Files:**
- `app/Exceptions/InvalidChoiceException.php`
- `app/Exceptions/ChoiceNotFoundException.php`
- `app/Exceptions/ChoiceNotUndoableException.php`
- `app/Exceptions/InvalidSelectionException.php`

```php
// InvalidChoiceException.php
<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;

class InvalidChoiceException extends ApiException
{
    public function __construct(
        public readonly string $choiceId,
        string $message = 'Invalid choice'
    ) {
        parent::__construct($message);
    }

    public function render($request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'choice_id' => $this->choiceId,
        ], 422);
    }
}
```

Similar pattern for other exceptions with appropriate HTTP status codes:
- `ChoiceNotFoundException` -> 404
- `ChoiceNotUndoableException` -> 422
- `InvalidSelectionException` -> 422

---

### Task 1.4: Create CharacterChoiceService

**File:** `app/Services/CharacterChoiceService.php`

```php
<?php

namespace App\Services;

use App\DTOs\PendingChoice;
use App\Exceptions\ChoiceNotFoundException;
use App\Exceptions\ChoiceNotUndoableException;
use App\Models\Character;
use App\Services\ChoiceHandlers\ChoiceTypeHandler;
use Illuminate\Support\Collection;

class CharacterChoiceService
{
    /** @var array<string, ChoiceTypeHandler> */
    private array $handlers = [];

    public function registerHandler(ChoiceTypeHandler $handler): void
    {
        $this->handlers[$handler->getType()] = $handler;
    }

    /**
     * Get all pending choices for a character.
     *
     * @return Collection<int, PendingChoice>
     */
    public function getPendingChoices(Character $character, ?string $type = null): Collection
    {
        $choices = collect();

        $handlersToQuery = $type !== null
            ? [$this->handlers[$type] ?? null]
            : array_values($this->handlers);

        foreach (array_filter($handlersToQuery) as $handler) {
            $choices = $choices->merge($handler->getChoices($character));
        }

        return $choices->values();
    }

    /**
     * Get a specific choice by ID.
     */
    public function getChoice(Character $character, string $choiceId): PendingChoice
    {
        $type = $this->parseChoiceType($choiceId);
        $handler = $this->handlers[$type] ?? null;

        if (!$handler) {
            throw new ChoiceNotFoundException($choiceId, "Unknown choice type: {$type}");
        }

        $choices = $handler->getChoices($character);
        $choice = $choices->first(fn(PendingChoice $c) => $c->id === $choiceId);

        if (!$choice) {
            throw new ChoiceNotFoundException($choiceId);
        }

        return $choice;
    }

    /**
     * Resolve a choice with the given selection.
     */
    public function resolveChoice(Character $character, string $choiceId, array $selection): void
    {
        $choice = $this->getChoice($character, $choiceId);
        $handler = $this->handlers[$choice->type];
        $handler->resolve($character, $choice, $selection);
    }

    /**
     * Check if a choice can be undone.
     */
    public function canUndoChoice(Character $character, string $choiceId): bool
    {
        $choice = $this->getChoice($character, $choiceId);
        $handler = $this->handlers[$choice->type];
        return $handler->canUndo($character, $choice);
    }

    /**
     * Undo a previously resolved choice.
     */
    public function undoChoice(Character $character, string $choiceId): void
    {
        $choice = $this->getChoice($character, $choiceId);
        $handler = $this->handlers[$choice->type];

        if (!$handler->canUndo($character, $choice)) {
            throw new ChoiceNotUndoableException($choiceId);
        }

        $handler->undo($character, $choice);
    }

    /**
     * Get summary of pending choices.
     */
    public function getSummary(Character $character): array
    {
        $choices = $this->getPendingChoices($character);
        $pending = $choices->filter(fn(PendingChoice $c) => $c->remaining > 0);

        return [
            'total_pending' => $pending->count(),
            'required_pending' => $pending->filter(fn(PendingChoice $c) => $c->required)->count(),
            'optional_pending' => $pending->filter(fn(PendingChoice $c) => !$c->required)->count(),
            'by_type' => $pending->groupBy('type')->map->count()->toArray(),
            'by_source' => $pending->groupBy('source')->map->count()->toArray(),
        ];
    }

    private function parseChoiceType(string $choiceId): string
    {
        $parts = explode(':', $choiceId);
        return $parts[0] ?? '';
    }
}
```

---

### Task 1.5: Create Controller and Resources

**File:** `app/Http/Controllers/Api/CharacterChoiceController.php`

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Character\ResolveChoiceRequest;
use App\Http\Resources\PendingChoiceResource;
use App\Http\Resources\PendingChoicesResource;
use App\Models\Character;
use App\Services\CharacterChoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CharacterChoiceController extends Controller
{
    public function __construct(
        private readonly CharacterChoiceService $choiceService
    ) {}

    /**
     * List all pending choices for a character.
     *
     * @queryParam type string Filter by choice type (proficiency, language, equipment, spell, asi_or_feat, subclass, optional_feature, expertise, fighting_style)
     */
    public function index(Request $request, Character $character): PendingChoicesResource
    {
        $type = $request->query('type');
        $choices = $this->choiceService->getPendingChoices($character, $type);
        $summary = $this->choiceService->getSummary($character);

        return new PendingChoicesResource($choices, $summary);
    }

    /**
     * Get a specific pending choice.
     */
    public function show(Character $character, string $choiceId): PendingChoiceResource
    {
        $choice = $this->choiceService->getChoice($character, $choiceId);
        return new PendingChoiceResource($choice);
    }

    /**
     * Resolve a pending choice.
     */
    public function resolve(ResolveChoiceRequest $request, Character $character, string $choiceId): JsonResponse
    {
        $this->choiceService->resolveChoice($character, $choiceId, $request->validated());

        return response()->json([
            'message' => 'Choice resolved successfully',
            'choice_id' => $choiceId,
        ]);
    }

    /**
     * Undo a resolved choice.
     */
    public function undo(Character $character, string $choiceId): JsonResponse
    {
        $this->choiceService->undoChoice($character, $choiceId);

        return response()->json([
            'message' => 'Choice undone successfully',
            'choice_id' => $choiceId,
        ]);
    }
}
```

**File:** `app/Http/Resources/PendingChoiceResource.php`

```php
<?php

namespace App\Http\Resources;

use App\DTOs\PendingChoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PendingChoiceResource extends JsonResource
{
    public function __construct(
        private readonly PendingChoice $choice
    ) {
        parent::__construct($choice);
    }

    public function toArray(Request $request): array
    {
        return $this->choice->toArray();
    }
}
```

**File:** `app/Http/Resources/PendingChoicesResource.php`

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

class PendingChoicesResource extends JsonResource
{
    public function __construct(
        private readonly Collection $choices,
        private readonly array $summary
    ) {
        parent::__construct($choices);
    }

    public function toArray(Request $request): array
    {
        return [
            'choices' => $this->choices->map(fn($c) => $c->toArray())->values(),
            'summary' => $this->summary,
        ];
    }
}
```

**File:** `app/Http/Requests/Character/ResolveChoiceRequest.php`

```php
<?php

namespace App\Http\Requests\Character;

use Illuminate\Foundation\Http\FormRequest;

class ResolveChoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Add proper authorization later
    }

    public function rules(): array
    {
        return [
            'selected' => ['sometimes', 'array'],
            'selected.*' => ['required', 'string'],
            // ASI/feat specific
            'type' => ['sometimes', 'in:asi,feat'],
            'feat_id' => ['required_if:type,feat', 'integer'],
            'increases' => ['required_if:type,asi', 'array'],
            'increases.*' => ['integer', 'min:1', 'max:2'],
        ];
    }
}
```

---

### Task 1.6: Add Routes

**File:** `routes/api.php` (add inside character group)

```php
// Unified Choice System
Route::get('pending-choices', [CharacterChoiceController::class, 'index'])
    ->name('pending-choices.index');
Route::get('pending-choices/{choiceId}', [CharacterChoiceController::class, 'show'])
    ->name('pending-choices.show');
Route::post('choices/{choiceId}', [CharacterChoiceController::class, 'resolve'])
    ->name('choices.resolve');
Route::delete('choices/{choiceId}', [CharacterChoiceController::class, 'undo'])
    ->name('choices.undo');
```

---

### Task 1.7: Register Service in Provider

**File:** `app/Providers/AppServiceProvider.php`

```php
// In register() method:
$this->app->singleton(CharacterChoiceService::class, function ($app) {
    $service = new CharacterChoiceService();

    // Register handlers (add as implemented)
    $service->registerHandler($app->make(ProficiencyChoiceHandler::class));
    $service->registerHandler($app->make(LanguageChoiceHandler::class));
    // ... more handlers

    return $service;
});
```

---

### Task 1.8: Write Feature Tests

**File:** `tests/Feature/Api/CharacterChoiceApiTest.php`

```php
<?php

use App\Models\Character;
use App\Models\CharacterClass;

describe('Character Pending Choices API', function () {
    it('returns empty choices for new character', function () {
        $character = Character::factory()->create();

        $response = $this->getJson("/api/v1/characters/{$character->id}/pending-choices");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'choices',
                    'summary' => [
                        'total_pending',
                        'required_pending',
                        'optional_pending',
                        'by_type',
                        'by_source',
                    ],
                ],
            ]);
    });

    it('filters choices by type', function () {
        $character = Character::factory()
            ->withRace('high-elf')
            ->withClass('rogue')
            ->create();

        $response = $this->getJson("/api/v1/characters/{$character->id}/pending-choices?type=proficiency");

        $response->assertOk();
        // All returned choices should be proficiency type
        collect($response->json('data.choices'))
            ->each(fn($choice) => expect($choice['type'])->toBe('proficiency'));
    });

    it('resolves a choice', function () {
        // Setup character with pending choice
        $character = Character::factory()
            ->withRace('high-elf')
            ->withClass('rogue')
            ->create();

        // Get a proficiency choice ID
        $choices = $this->getJson("/api/v1/characters/{$character->id}/pending-choices?type=proficiency")
            ->json('data.choices');

        $choiceId = $choices[0]['id'] ?? null;
        expect($choiceId)->not->toBeNull();

        // Resolve with valid selection
        $response = $this->postJson(
            "/api/v1/characters/{$character->id}/choices/{$choiceId}",
            ['selected' => ['stealth', 'sleight-of-hand']]
        );

        $response->assertOk()
            ->assertJson(['message' => 'Choice resolved successfully']);
    });

    it('returns 404 for unknown choice ID', function () {
        $character = Character::factory()->create();

        $response = $this->getJson("/api/v1/characters/{$character->id}/pending-choices/invalid:choice:id");

        $response->assertNotFound();
    });

    it('returns 422 for invalid selection', function () {
        $character = Character::factory()
            ->withRace('high-elf')
            ->withClass('rogue')
            ->create();

        $choices = $this->getJson("/api/v1/characters/{$character->id}/pending-choices?type=proficiency")
            ->json('data.choices');

        $choiceId = $choices[0]['id'] ?? null;

        $response = $this->postJson(
            "/api/v1/characters/{$character->id}/choices/{$choiceId}",
            ['selected' => ['invalid-skill']]
        );

        $response->assertUnprocessable();
    });
});
```

---

## Phase 2: Migrate Existing Choice Types

### Task 2.1: Proficiency Choice Handler (#249)

**File:** `app/Services/ChoiceHandlers/ProficiencyChoiceHandler.php`

Wraps existing `CharacterProficiencyService`:
- `getChoices()` - Transform `getPendingChoices()` output to `Collection<PendingChoice>`
- `resolve()` - Delegate to `makeSkillChoice()` or `makeProficiencyTypeChoice()`
- `canUndo()` - Return true (proficiencies can be changed)
- `undo()` - Clear choices for the choice group

**Choice ID format:** `proficiency:class:5:1:skill_choice_1`

---

### Task 2.2: Language Choice Handler (#250)

**File:** `app/Services/ChoiceHandlers/LanguageChoiceHandler.php`

Wraps existing `CharacterLanguageService`:
- `getChoices()` - Transform `getPendingChoices()` output
- `resolve()` - Delegate to `makeChoice()`
- `canUndo()` - Return true
- `undo()` - Clear language choices from source

**Choice ID format:** `language:race:7:1:language_choice`

---

### Task 2.3: ASI/Feat Choice Handler (#257)

**File:** `app/Services/ChoiceHandlers/AsiChoiceHandler.php`

Wraps existing `AsiChoiceService`:
- `getChoices()` - Check `asi_choices_remaining` on character
- `resolve()` - Delegate to `applyFeatChoice()` or `applyAbilityIncrease()`
- `canUndo()` - Return false (ASI changes are permanent for now)
- `undo()` - Throw ChoiceNotUndoableException

**Choice ID format:** `asi_or_feat:class:5:4:asi_1`

---

### Task 2.4: Optional Feature Choice Handler (#259)

**File:** `app/Services/ChoiceHandlers/OptionalFeatureChoiceHandler.php`

Wraps existing `FeatureSelectionController` logic:
- `getChoices()` - Transform available feature selections
- `resolve()` - Create FeatureSelection record
- `canUndo()` - Return true (invocations can be swapped)
- `undo()` - Delete FeatureSelection record

**Choice ID format:** `optional_feature:class:5:2:invocation_1`

---

## Phase 3: Add Missing Choice Types

### Task 3.1: Equipment Choice Handler (#251)

**File:** `app/Services/ChoiceHandlers/EquipmentChoiceHandler.php`

New functionality - parse class/background starting equipment choices:
- Parse `equipment_choices` from class/background
- Generate choices at character creation (level 1)
- Resolve creates CharacterEquipment records

**Choice ID format:** `equipment:class:5:1:equipment_choice_1`

---

### Task 3.2: Subclass Choice Handler (#252)

**File:** `app/Services/ChoiceHandlers/SubclassChoiceHandler.php`

Check if character needs to select subclass:
- Cleric, Sorcerer, Warlock: Level 1
- Most classes: Level 3
- Generate choice when level requirement met and no subclass set

**Choice ID format:** `subclass:class:5:1:subclass`

---

### Task 3.3: Spell Choice Handler (#253)

**File:** `app/Services/ChoiceHandlers/SpellChoiceHandler.php`

Track spell selections needed:
- Cantrips known
- Spells known (for known-casters like Bard, Ranger, Sorcerer)
- Level-up spell additions

**Choice ID format:** `spell:class:5:1:cantrips` or `spell:class:5:3:spells_known`

---

### Task 3.4: Expertise Choice Handler (#260)

**File:** `app/Services/ChoiceHandlers/ExpertiseChoiceHandler.php`

Rogue/Bard expertise selections:
- Rogue: Level 1 (2), Level 6 (2)
- Bard: Level 3 (2), Level 10 (2)

**Choice ID format:** `expertise:class:5:1:expertise_1`

---

### Task 3.5: Fighting Style Choice Handler (#261)

**File:** `app/Services/ChoiceHandlers/FightingStyleChoiceHandler.php`

Fighting style selections:
- Fighter: Level 1
- Ranger: Level 2
- Paladin: Level 2

**Choice ID format:** `fighting_style:class:5:1:fighting_style`

---

## Quality Gates

After each phase:

1. **Tests pass:**
   ```bash
   sail artisan test --testsuite=Feature-DB
   sail artisan test --testsuite=Unit-DB
   ```

2. **Code formatted:**
   ```bash
   sail composer pint
   ```

3. **Static analysis (if configured):**
   ```bash
   sail composer analyse
   ```

4. **Update CHANGELOG.md**

---

## Rollout Plan

1. **Phase 1 complete:** New endpoints available, no handlers = empty responses
2. **Phase 2 complete:** Existing choice types migrated, old endpoints deprecated
3. **Phase 3 complete:** All choice types unified
4. **Future:** Remove deprecated endpoints in v2

---

## File Summary

### New Files (Phase 1)
```
app/DTOs/PendingChoice.php
app/Services/CharacterChoiceService.php
app/Services/ChoiceHandlers/ChoiceTypeHandler.php
app/Exceptions/InvalidChoiceException.php
app/Exceptions/ChoiceNotFoundException.php
app/Exceptions/ChoiceNotUndoableException.php
app/Exceptions/InvalidSelectionException.php
app/Http/Controllers/Api/CharacterChoiceController.php
app/Http/Resources/PendingChoiceResource.php
app/Http/Resources/PendingChoicesResource.php
app/Http/Requests/Character/ResolveChoiceRequest.php
tests/Feature/Api/CharacterChoiceApiTest.php
tests/Unit/DTOs/PendingChoiceTest.php
tests/Unit/Services/CharacterChoiceServiceTest.php
```

### New Files (Phase 2)
```
app/Services/ChoiceHandlers/ProficiencyChoiceHandler.php
app/Services/ChoiceHandlers/LanguageChoiceHandler.php
app/Services/ChoiceHandlers/AsiChoiceHandler.php
app/Services/ChoiceHandlers/OptionalFeatureChoiceHandler.php
tests/Unit/Services/ChoiceHandlers/ProficiencyChoiceHandlerTest.php
tests/Unit/Services/ChoiceHandlers/LanguageChoiceHandlerTest.php
tests/Unit/Services/ChoiceHandlers/AsiChoiceHandlerTest.php
tests/Unit/Services/ChoiceHandlers/OptionalFeatureChoiceHandlerTest.php
```

### New Files (Phase 3)
```
app/Services/ChoiceHandlers/EquipmentChoiceHandler.php
app/Services/ChoiceHandlers/SubclassChoiceHandler.php
app/Services/ChoiceHandlers/SpellChoiceHandler.php
app/Services/ChoiceHandlers/ExpertiseChoiceHandler.php
app/Services/ChoiceHandlers/FightingStyleChoiceHandler.php
tests/Unit/Services/ChoiceHandlers/EquipmentChoiceHandlerTest.php
tests/Unit/Services/ChoiceHandlers/SubclassChoiceHandlerTest.php
tests/Unit/Services/ChoiceHandlers/SpellChoiceHandlerTest.php
tests/Unit/Services/ChoiceHandlers/ExpertiseChoiceHandlerTest.php
tests/Unit/Services/ChoiceHandlers/FightingStyleChoiceHandlerTest.php
```

### Modified Files
```
routes/api.php
app/Providers/AppServiceProvider.php
CHANGELOG.md
```
