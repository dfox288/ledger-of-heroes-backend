<?php

declare(strict_types=1);

namespace App\Services\WizardFlowTesting;

use App\Models\Background;
use App\Models\CharacterClass;
use App\Models\Race;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Executes wizard flow steps via internal Laravel routing.
 * Uses app()->handle() to avoid network overhead and container connectivity issues.
 */
class FlowExecutor
{
    private StateSnapshot $snapshot;

    private SwitchValidator $validator;

    // Track current selections for switches
    private ?Race $currentRace = null;

    private ?Race $currentSubrace = null;

    private ?CharacterClass $currentClass = null;

    private ?CharacterClass $currentSubclass = null;

    private ?Background $currentBackground = null;

    private ?string $equipmentMode = null;

    private ?string $previousEquipmentMode = null;

    private EquipmentValidator $equipmentValidator;

    private SubclassValidator $subclassValidator;

    /** @var array<string, array<string>> Equipment selections by choice_group */
    private array $equipmentSelections = [];

    public function __construct()
    {
        $this->snapshot = new StateSnapshot;
        $this->validator = new SwitchValidator;
        $this->equipmentValidator = new EquipmentValidator;
        $this->subclassValidator = new SubclassValidator;
    }

    /**
     * Execute a complete wizard flow.
     */
    public function execute(array $flow, CharacterRandomizer $randomizer, int $iteration = 1): FlowResult
    {
        $result = new FlowResult($iteration, $randomizer->getSeed());
        $characterId = null;

        // Reset state for new execution
        $this->resetState();

        foreach ($flow as $step) {
            try {
                // Capture state before if this is a switch, equipment-related, or subclass step
                $snapshotBefore = null;
                if ($characterId && ($this->isSwitch($step) || $this->shouldValidateEquipment($step) || $this->shouldValidateSubclass($step))) {
                    $snapshotBefore = $this->snapshot->capture($characterId);
                }

                // Execute the step
                $response = $this->executeStep($step, $characterId, $randomizer);

                // Handle character creation
                if ($step['action'] === 'create' && isset($response['data']['id'])) {
                    $characterId = $response['data']['id'];
                    $publicId = $response['data']['public_id'];
                    $result->setCharacter($characterId, $publicId);
                }

                // Skip conditional steps that don't apply
                if ($response === null) {
                    $result->addStep($step, 'skipped');

                    continue;
                }

                // Check for HTTP errors
                if (isset($response['error']) && $response['error']) {
                    $result->addStep($step, 'http_error', null, ['status' => $response['status'] ?? 500]);
                    $result->addError($step, new \RuntimeException($response['message'] ?? 'HTTP error'));

                    break;
                }

                // Capture state after
                $snapshotAfter = $characterId ? $this->snapshot->capture($characterId) : null;

                // Validate switch behavior
                if ($this->isSwitch($step) && $snapshotBefore && $snapshotAfter) {
                    $validation = $this->validator->validate(
                        $step['action'],
                        $snapshotBefore,
                        $snapshotAfter,
                        $this->equipmentMode
                    );

                    if (! $validation->passed) {
                        $result->addStep($step, 'fail', $snapshotAfter, $response);
                        $result->addFailure($step, $validation, $snapshotBefore, $snapshotAfter);

                        continue; // Continue to find more issues
                    }
                }

                // Validate equipment after mode selection or equipment choices
                if ($snapshotAfter && $this->shouldValidateEquipment($step)) {
                    $equipmentValidation = $this->validateEquipmentAfterStep($step, $snapshotBefore, $snapshotAfter);

                    if ($equipmentValidation && ! $equipmentValidation->passed) {
                        $result->addStep($step, 'fail', $snapshotAfter, $response);
                        $result->addFailure($step, $equipmentValidation, $snapshotBefore, $snapshotAfter);

                        continue;
                    }
                }

                // Validate subclass features after subclass selection
                if ($snapshotAfter && $this->shouldValidateSubclass($step) && $this->currentSubclass) {
                    $subclassValidation = $this->subclassValidator->validateSubclassFeatures(
                        $snapshotAfter,
                        $this->currentSubclass,
                        1 // Level 1 for now
                    );

                    if (! $subclassValidation->passed) {
                        $result->addStep($step, 'fail', $snapshotAfter, $response);
                        $result->addFailure($step, $subclassValidation, $snapshotBefore, $snapshotAfter);

                        continue;
                    }
                }

                $result->addStep($step, 'ok', $snapshotAfter, $response);

            } catch (\Throwable $e) {
                Log::error('Wizard flow step failed', [
                    'step' => $step,
                    'character_id' => $characterId,
                    'exception' => $e->getMessage(),
                ]);

                $result->addError($step, $e);

                break;
            }
        }

        return $result;
    }

    /**
     * Execute a single step.
     */
    private function executeStep(array $step, ?int $characterId, CharacterRandomizer $randomizer): ?array
    {
        return match ($step['action']) {
            'create' => $this->createCharacter($randomizer),
            'set_race' => $this->setRace($characterId, $randomizer, $step['force_race'] ?? null),
            'set_subrace' => $this->setSubrace($characterId, $randomizer),
            'set_class' => $this->setClass($characterId, $randomizer, $step['force_class'] ?? null),
            'set_subclass' => $this->setSubclass($characterId, $randomizer),
            'set_background' => $this->setBackground($characterId, $randomizer),
            'set_ability_scores' => $this->setAbilityScores($characterId, $randomizer),
            'resolve_proficiency_choices' => $this->resolveChoices($characterId, $randomizer, 'proficiency'),
            'resolve_language_choices' => $this->resolveChoices($characterId, $randomizer, 'language'),
            'set_equipment_mode' => $this->setEquipmentMode($characterId, $randomizer),
            'resolve_equipment_choices' => $this->resolveChoices($characterId, $randomizer, 'equipment'),
            'resolve_spell_choices' => $this->resolveChoices($characterId, $randomizer, 'spell'),
            'set_details' => $this->setDetails($characterId, $randomizer),
            'validate' => $this->validateCharacter($characterId),
            'switch_race' => $this->switchRace($characterId, $randomizer),
            'switch_background' => $this->switchBackground($characterId, $randomizer),
            'switch_class' => $this->switchClass($characterId, $randomizer, $step),
            default => throw new \RuntimeException("Unknown action: {$step['action']}"),
        };
    }

    private function createCharacter(CharacterRandomizer $randomizer): array
    {
        return $this->makeRequest('POST', '/api/v1/characters', [
            'public_id' => $randomizer->generatePublicId(),
            'name' => $randomizer->randomName(),
        ]);
    }

    private function setRace(int $characterId, CharacterRandomizer $randomizer, ?string $forceRace = null): array
    {
        if ($forceRace) {
            $race = Race::where('slug', $forceRace)->firstOrFail();
        } else {
            $race = $randomizer->randomRace();
        }

        $this->currentRace = $race;
        $this->currentSubrace = null;

        return $this->makeRequest('PATCH', "/api/v1/characters/{$characterId}", [
            'race_slug' => $race->slug,
        ]);
    }

    private function setSubrace(int $characterId, CharacterRandomizer $randomizer): ?array
    {
        if (! $this->currentRace || $this->currentRace->subraces->isEmpty()) {
            return null; // Skip - no subraces available
        }

        $subrace = $randomizer->randomSubrace($this->currentRace);
        if (! $subrace) {
            return null;
        }

        $this->currentSubrace = $subrace;

        return $this->makeRequest('PATCH', "/api/v1/characters/{$characterId}", [
            'race_slug' => $subrace->slug,
        ]);
    }

    private function setClass(int $characterId, CharacterRandomizer $randomizer, ?string $forceClass = null): array
    {
        if ($forceClass) {
            $class = CharacterClass::where('slug', $forceClass)->firstOrFail();
        } else {
            $class = $randomizer->randomClass();
        }

        $this->currentClass = $class;

        // If already has a class, replace it
        if ($this->characterHasClass($characterId)) {
            $currentClassSlug = $this->getCurrentClassSlug($characterId);

            return $this->makeRequest('PUT', "/api/v1/characters/{$characterId}/classes/{$currentClassSlug}", [
                'class_slug' => $class->slug,
            ]);
        } else {
            return $this->makeRequest('POST', "/api/v1/characters/{$characterId}/classes", [
                'class_slug' => $class->slug,
                'force' => true,
            ]);
        }
    }

    private function setSubclass(int $characterId, CharacterRandomizer $randomizer): ?array
    {
        if (! $this->currentClass || $this->currentClass->subclass_level !== 1) {
            return null; // Skip - no subclass at level 1
        }

        $subclasses = $this->currentClass->subclasses;
        if ($subclasses->isEmpty()) {
            return null;
        }

        $subclass = $subclasses[$randomizer->randomInt(0, $subclasses->count() - 1)];
        $this->currentSubclass = $subclass;

        return $this->makeRequest(
            'PUT',
            "/api/v1/characters/{$characterId}/classes/{$this->currentClass->slug}/subclass",
            ['subclass_slug' => $subclass->slug]
        );
    }

    private function setBackground(int $characterId, CharacterRandomizer $randomizer): array
    {
        $background = $randomizer->randomBackground();
        $this->currentBackground = $background;

        return $this->makeRequest('PATCH', "/api/v1/characters/{$characterId}", [
            'background_slug' => $background->slug,
        ]);
    }

    private function setAbilityScores(int $characterId, CharacterRandomizer $randomizer): array
    {
        $scores = $randomizer->randomAbilityScores();

        return $this->makeRequest('PATCH', "/api/v1/characters/{$characterId}", $scores);
    }

    private function resolveChoices(int $characterId, CharacterRandomizer $randomizer, string $type): ?array
    {
        // Get pending choices of this type
        $choicesResponse = $this->makeRequest('GET', "/api/v1/characters/{$characterId}/pending-choices");

        // Response structure is data.choices
        $allChoices = $choicesResponse['data']['choices'] ?? [];

        // Filter by type
        $choices = array_filter($allChoices, fn ($c) => ($c['type'] ?? '') === $type);

        if (empty($choices)) {
            return null; // Skip - no choices to resolve
        }

        $lastResponse = null;

        // Track already-selected values to avoid collisions between choices
        // (e.g., selecting the same language for both race and background)
        $alreadySelected = [];

        foreach ($choices as $choice) {
            $choiceId = $choice['id'];
            $options = $choice['options'] ?? [];
            $choiceType = $choice['type'] ?? '';

            // Skip if no options or if options need to be fetched from endpoint
            if (empty($options)) {
                // For spells, we need to fetch from endpoint
                if ($choiceType === 'spell' && ! empty($choice['options_endpoint'])) {
                    $spellsResponse = $this->makeRequest('GET', $choice['options_endpoint']);
                    $options = $spellsResponse['data'] ?? [];
                }

                if (empty($options)) {
                    continue;
                }
            }

            // Determine what to select based on choice type
            $count = $choice['quantity'] ?? $choice['remaining'] ?? 1;

            if ($choiceType === 'equipment') {
                // Equipment uses 'option' field (a, b, c, etc.)
                // Pick a random option (could be fixed items or category)
                $optionValues = array_column($options, 'option');
                $selected = $randomizer->pickRandom($optionValues, 1);

                // Find the selected option to check if it's a category
                $selectedOption = $selected[0] ?? null;
                $foundOption = null;
                foreach ($options as $opt) {
                    if (($opt['option'] ?? '') === $selectedOption) {
                        $foundOption = $opt;
                        break;
                    }
                }

                // Track what items we expect to get from this choice
                $choiceGroup = $choice['metadata']['choice_group'] ?? $choiceId;
                $expectedItems = [];

                // If it's a category option, we need to provide item_selections
                $itemSelections = null;
                if ($foundOption && ($foundOption['is_category'] ?? false)) {
                    // Get non-fixed items (the ones user must choose from)
                    $selectableItems = array_filter(
                        $foundOption['items'] ?? [],
                        fn ($item) => ! ($item['is_fixed'] ?? false)
                    );

                    if (! empty($selectableItems)) {
                        // Pick one random item from the category
                        $itemSlugs = array_column($selectableItems, 'slug');
                        $pickedItem = $randomizer->pickRandom($itemSlugs, 1);
                        $itemSelections = [$selectedOption => $pickedItem];
                        $expectedItems = array_merge($expectedItems, $pickedItem);
                    }

                    // Also include fixed items that come with this option
                    $fixedItems = array_filter(
                        $foundOption['items'] ?? [],
                        fn ($item) => ($item['is_fixed'] ?? false) && ! ($item['is_pack'] ?? false)
                    );
                    foreach ($fixedItems as $item) {
                        $expectedItems[] = $item['slug'];
                    }
                } else {
                    // Non-category option: all items are granted
                    foreach ($foundOption['items'] ?? [] as $item) {
                        if (! ($item['is_pack'] ?? false)) {
                            $expectedItems[] = $item['slug'];
                        }
                        // For packs, add the contents
                        if (($item['is_pack'] ?? false) && ! empty($item['contents'])) {
                            foreach ($item['contents'] as $content) {
                                $expectedItems[] = $content['slug'];
                            }
                        }
                    }
                }

                // Track the selections for validation
                $this->equipmentSelections[$choiceGroup] = $expectedItems;

                // Build the request payload
                $payload = ['selected' => $selected];
                if ($itemSelections !== null) {
                    $payload['item_selections'] = $itemSelections;
                }

                $lastResponse = $this->makeRequest('POST', "/api/v1/characters/{$characterId}/choices/{$choiceId}", $payload);

                if (isset($lastResponse['error']) && $lastResponse['error']) {
                    return $lastResponse;
                }

                continue; // Skip the generic POST below since we already made the request
            } elseif ($choiceType === 'proficiency' || $choiceType === 'language') {
                // Proficiencies and languages use 'slug'
                $slugs = array_map(fn ($o) => $o['slug'] ?? '', $options);
                $slugs = array_filter($slugs);
                // Exclude already-selected values to avoid duplicates across choices
                $slugs = array_diff($slugs, $alreadySelected);
                $selected = $randomizer->pickRandom(array_values($slugs), min($count, count($slugs)));
            } elseif ($choiceType === 'spell') {
                // Spells use 'slug'
                $slugs = array_map(fn ($o) => $o['slug'] ?? '', $options);
                $slugs = array_filter($slugs);
                // Exclude already-selected spells to avoid duplicates
                $slugs = array_diff($slugs, $alreadySelected);
                $selected = $randomizer->pickRandom(array_values($slugs), min($count, count($slugs)));
            } else {
                // Generic fallback
                $slugs = array_map(fn ($o) => $o['slug'] ?? $o['value'] ?? '', $options);
                $slugs = array_filter($slugs);
                $selected = $randomizer->pickRandom($slugs, min($count, count($slugs)));
            }

            if (empty($selected)) {
                continue;
            }

            // Track what we selected to avoid duplicates in subsequent choices
            foreach ((array) $selected as $sel) {
                $alreadySelected[] = $sel;
            }

            $lastResponse = $this->makeRequest('POST', "/api/v1/characters/{$characterId}/choices/{$choiceId}", [
                'selected' => $selected,
            ]);

            if (isset($lastResponse['error']) && $lastResponse['error']) {
                return $lastResponse;
            }
        }

        return $lastResponse ?? ['data' => [], 'message' => 'No choices resolved'];
    }

    private function setEquipmentMode(int $characterId, CharacterRandomizer $randomizer): ?array
    {
        // Track previous mode for validation
        $this->previousEquipmentMode = $this->equipmentMode;
        $this->equipmentMode = $randomizer->randomEquipmentMode();

        // Check if there's an equipment mode choice pending
        $choicesResponse = $this->makeRequest('GET', "/api/v1/characters/{$characterId}/pending-choices");

        $allChoices = $choicesResponse['data']['choices'] ?? [];
        $choices = array_filter($allChoices, fn ($c) => ($c['type'] ?? '') === 'equipment_mode');

        if (empty($choices)) {
            return null;
        }

        $choice = array_values($choices)[0];

        // Clear equipment selections when switching modes (they'll be re-made if equipment mode)
        $this->equipmentSelections = [];

        return $this->makeRequest('POST', "/api/v1/characters/{$characterId}/choices/{$choice['id']}", [
            'selected' => [$this->equipmentMode],
        ]);
    }

    private function setDetails(int $characterId, CharacterRandomizer $randomizer): array
    {
        $alignments = [
            'Lawful Good', 'Neutral Good', 'Chaotic Good',
            'Lawful Neutral', 'True Neutral', 'Chaotic Neutral',
            'Lawful Evil', 'Neutral Evil', 'Chaotic Evil',
        ];

        return $this->makeRequest('PATCH', "/api/v1/characters/{$characterId}", [
            'name' => $randomizer->randomName(),
            'alignment' => $alignments[$randomizer->randomInt(0, count($alignments) - 1)],
        ]);
    }

    private function validateCharacter(int $characterId): array
    {
        return $this->makeRequest('GET', "/api/v1/characters/{$characterId}/validate");
    }

    private function switchRace(int $characterId, CharacterRandomizer $randomizer): array
    {
        $newRace = $randomizer->differentRace($this->currentRace);
        $this->currentRace = $newRace;
        $this->currentSubrace = null;

        return $this->makeRequest('PATCH', "/api/v1/characters/{$characterId}", [
            'race_slug' => $newRace->slug,
        ]);
    }

    private function switchBackground(int $characterId, CharacterRandomizer $randomizer): array
    {
        $newBackground = $randomizer->differentBackground($this->currentBackground);
        $this->currentBackground = $newBackground;

        return $this->makeRequest('PATCH', "/api/v1/characters/{$characterId}", [
            'background_slug' => $newBackground->slug,
        ]);
    }

    private function switchClass(int $characterId, CharacterRandomizer $randomizer, array $step): array
    {
        $spellcaster = null;
        if (isset($step['class_type_to'])) {
            $spellcaster = $step['class_type_to'] === 'spellcaster';
        }

        $newClass = $randomizer->differentClass($this->currentClass, $spellcaster);
        $oldClassSlug = $this->currentClass->slug;
        $this->currentClass = $newClass;

        return $this->makeRequest('PUT', "/api/v1/characters/{$characterId}/classes/{$oldClassSlug}", [
            'class_slug' => $newClass->slug,
        ]);
    }

    /**
     * Make an internal request to the Laravel application.
     */
    private function makeRequest(string $method, string $uri, array $data = []): array
    {
        // Build the request
        $request = Request::create(
            $uri,
            $method,
            $method === 'GET' ? $data : [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            $method !== 'GET' ? json_encode($data) : null
        );

        // Handle the request through the application
        $response = app()->handle($request);

        // Get the response content
        $content = $response->getContent();
        $statusCode = $response->getStatusCode();

        // Parse JSON response
        $decoded = json_decode($content, true) ?? [];

        // Check for error status codes
        if ($statusCode >= 400) {
            return [
                'error' => true,
                'status' => $statusCode,
                'message' => $decoded['message'] ?? 'Request failed',
                'errors' => $decoded['errors'] ?? [],
            ];
        }

        return $decoded;
    }

    private function isSwitch(array $step): bool
    {
        return ($step['is_switch'] ?? false) || str_starts_with($step['action'], 'switch_');
    }

    private function characterHasClass(int $characterId): bool
    {
        $response = $this->makeRequest('GET', "/api/v1/characters/{$characterId}");
        $classes = $response['data']['classes'] ?? [];

        return ! empty($classes);
    }

    private function getCurrentClassSlug(int $characterId): ?string
    {
        $response = $this->makeRequest('GET', "/api/v1/characters/{$characterId}");
        $classes = $response['data']['classes'] ?? [];

        return $classes[0]['class']['slug'] ?? null;
    }

    private function resetState(): void
    {
        $this->currentRace = null;
        $this->currentSubrace = null;
        $this->currentClass = null;
        $this->currentSubclass = null;
        $this->currentBackground = null;
        $this->equipmentMode = null;
        $this->previousEquipmentMode = null;
        $this->equipmentSelections = [];
    }

    /**
     * Determine if equipment should be validated after this step.
     */
    private function shouldValidateEquipment(array $step): bool
    {
        $action = $step['action'];

        return in_array($action, [
            'set_equipment_mode',
            'resolve_equipment_choices',
        ], true);
    }

    /**
     * Determine if subclass features should be validated after this step.
     */
    private function shouldValidateSubclass(array $step): bool
    {
        return $step['action'] === 'set_subclass';
    }

    /**
     * Validate equipment state after a step.
     */
    private function validateEquipmentAfterStep(array $step, ?array $before, array $after): ?ValidationResult
    {
        $action = $step['action'];

        // Get class and background slugs
        $classSlug = $this->currentClass?->slug;
        $backgroundSlug = $this->currentBackground?->slug;

        if ($action === 'set_equipment_mode') {
            // Validate mode switch if switching between modes
            if ($this->previousEquipmentMode !== null && $this->previousEquipmentMode !== $this->equipmentMode && $before) {
                $modeSwitchValidation = $this->equipmentValidator->validateModeSwitch(
                    $before,
                    $after,
                    $this->previousEquipmentMode,
                    $this->equipmentMode
                );

                if (! $modeSwitchValidation->passed) {
                    return $modeSwitchValidation;
                }
            }

            // If switching to equipment mode, validate choices are available
            if ($this->equipmentMode === 'equipment') {
                $pendingChoices = $after['pending_choices']['data']['choices'] ?? [];

                return $this->equipmentValidator->validateEquipmentChoicesAvailable(
                    $pendingChoices,
                    $classSlug
                );
            }

            // If gold mode, validate gold state
            if ($this->equipmentMode === 'gold') {
                return $this->equipmentValidator->validateEquipmentState(
                    $after,
                    'gold',
                    [],
                    $classSlug,
                    $backgroundSlug
                );
            }
        }

        if ($action === 'resolve_equipment_choices') {
            // Check if all equipment choices are resolved (no more pending)
            $pendingChoices = $after['pending_choices']['data']['choices'] ?? [];
            $pendingEquipmentChoices = array_filter(
                $pendingChoices,
                fn ($c) => ($c['type'] ?? '') === 'equipment'
            );
            $allChoicesResolved = empty($pendingEquipmentChoices);

            // Validate equipment state after resolving choices
            return $this->equipmentValidator->validateEquipmentState(
                $after,
                $this->equipmentMode ?? 'equipment',
                $this->equipmentSelections,
                $classSlug,
                $backgroundSlug,
                $allChoicesResolved
            );
        }

        return null;
    }
}
