<?php

declare(strict_types=1);

namespace App\Services\LevelUpFlowTesting;

use App\Models\CharacterClass;
use App\Services\WizardFlowTesting\CharacterRandomizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Executes level-up flow testing for characters.
 *
 * Levels a character from their current level to a target level,
 * resolving all pending choices along the way and validating each step.
 */
class LevelUpFlowExecutor
{
    private LevelUpStateSnapshot $snapshot;

    private LevelUpValidator $validator;

    private ?string $forceSubclass = null;

    public function __construct()
    {
        $this->snapshot = new LevelUpStateSnapshot;
        $this->validator = new LevelUpValidator;
    }

    /**
     * Execute level-up flow for a character.
     *
     * @param  int  $characterId  ID of the character to level
     * @param  int  $targetLevel  Target total level (default 20)
     * @param  CharacterRandomizer  $randomizer  For random choices
     * @param  int  $iteration  Iteration number for reporting
     * @param  string  $mode  Mode: 'linear', 'chaos', or 'realistic'
     * @param  string|null  $forceSubclass  Force a specific subclass when the choice appears
     */
    public function execute(
        int $characterId,
        int $targetLevel,
        CharacterRandomizer $randomizer,
        int $iteration = 1,
        string $mode = 'linear',
        ?string $forceSubclass = null,
    ): LevelUpFlowResult {
        $this->forceSubclass = $forceSubclass;

        // Get character info
        $characterResponse = $this->makeRequest('GET', "/api/v1/characters/{$characterId}");

        if (isset($characterResponse['error'])) {
            $result = new LevelUpFlowResult(
                $iteration,
                $randomizer->getSeed(),
                $characterId,
                $characterResponse['data']['public_id'] ?? 'unknown'
            );
            $result->setError(1, new \RuntimeException($characterResponse['message'] ?? 'Failed to get character'));

            return $result;
        }

        $character = $characterResponse['data'];
        $publicId = $character['public_id'];
        $currentLevel = $character['total_level'] ?? 1;

        // Verify character is complete
        if (! ($character['is_complete'] ?? false)) {
            $result = new LevelUpFlowResult($iteration, $randomizer->getSeed(), $characterId, $publicId);
            $result->setError($currentLevel, new \RuntimeException('Character is not complete - cannot level up'));

            return $result;
        }

        $result = new LevelUpFlowResult($iteration, $randomizer->getSeed(), $characterId, $publicId);

        // Get the character's classes for level-up decisions
        $classes = $character['classes'] ?? [];
        if (empty($classes)) {
            $result->setError($currentLevel, new \RuntimeException('Character has no class'));

            return $result;
        }

        // Track class levels for multiclass decisions
        $classLevels = [];
        foreach ($classes as $classData) {
            $classLevels[$classData['class']['slug']] = $classData['level'];
        }

        // Generate multiclass plan for realistic mode
        $multiclassPlan = $mode === 'realistic'
            ? $this->generateRealisticPlan($classLevels, $targetLevel, $currentLevel, $randomizer)
            : null;

        // Level up from current to target
        for ($level = $currentLevel + 1; $level <= $targetLevel; $level++) {
            try {
                // Check if we should add a multiclass at this level
                $shouldMulticlass = match ($mode) {
                    'chaos' => $level > 2 && $randomizer->randomInt(1, 100) <= 20,
                    'realistic' => $multiclassPlan !== null && in_array($level, $multiclassPlan['multiclass_levels'], true),
                    default => false,
                };

                if ($shouldMulticlass) {
                    $multiclassResult = $this->tryAddMulticlass($characterId, $classLevels, $randomizer);
                    if ($multiclassResult !== null) {
                        // Adding a multiclass creates the class at level 1, so:
                        // - Track that this new class is at level 1
                        $classLevels[$multiclassResult] = 1;
                        // - Skip this level since the multiclass used it
                        // - Adding a multiclass may introduce new requirements - resolve them
                        $this->resolveAllPendingChoices($characterId, $randomizer);

                        // Skip to next level since multiclass consumed this level
                        continue;
                    }
                }

                // Select which class to level
                $classToLevel = $this->selectClassToLevel($classLevels, $mode !== 'linear', $randomizer);

                // Capture state before
                $snapshotBefore = $this->snapshot->capture($characterId);
                $hpBefore = $snapshotBefore['level_up_derived']['max_hp'] ?? 0;

                // Execute level-up
                $levelUpResponse = $this->makeRequest(
                    'POST',
                    "/api/v1/characters/{$characterId}/classes/{$classToLevel}/level-up"
                );

                if (isset($levelUpResponse['error'])) {
                    $stepResult = LevelUpStepResult::failure(
                        level: $level,
                        classSlug: $classToLevel,
                        errors: [$levelUpResponse['message'] ?? 'Level-up failed'],
                        pattern: 'api_error',
                        beforeSnapshot: $snapshotBefore
                    );
                    $result->addStep($stepResult);

                    break; // Stop leveling after API error - can't skip levels
                }

                // Resolve all pending choices
                $this->resolveAllPendingChoices($characterId, $randomizer);

                // Capture state after
                $snapshotAfter = $this->snapshot->capture($characterId);
                $hpAfter = $snapshotAfter['level_up_derived']['max_hp'] ?? 0;
                $hpGained = $hpAfter - $hpBefore;

                // Extract features gained
                $featuresGained = $levelUpResponse['data']['features_gained'] ?? [];
                $featureSlugs = array_column($featuresGained, 'slug');

                // Update class levels tracking
                $classLevels[$classToLevel] = ($classLevels[$classToLevel] ?? 0) + 1;

                // Validate the level-up
                $validation = $this->validator->validateLevelUp(
                    $snapshotBefore,
                    $snapshotAfter,
                    $classToLevel,
                    $level
                );

                if (! $validation->passed) {
                    $stepResult = LevelUpStepResult::failure(
                        level: $level,
                        classSlug: $classToLevel,
                        errors: $validation->errors,
                        pattern: $validation->pattern,
                        warnings: $validation->warnings,
                        beforeSnapshot: $snapshotBefore,
                        afterSnapshot: $snapshotAfter
                    );
                } else {
                    $stepResult = LevelUpStepResult::success(
                        level: $level,
                        classSlug: $classToLevel,
                        hpGained: $hpGained,
                        featuresGained: $featureSlugs,
                        warnings: $validation->warnings,
                        beforeSnapshot: $snapshotBefore,
                        afterSnapshot: $snapshotAfter
                    );
                }

                $result->addStep($stepResult);

            } catch (\Throwable $e) {
                Log::error('Level-up flow step failed', [
                    'character_id' => $characterId,
                    'level' => $level,
                    'class_slug' => $classToLevel ?? 'unknown',
                    'exception' => $e->getMessage(),
                ]);

                $result->setError($level, $e);

                break;
            }
        }

        return $result;
    }

    /**
     * Select which class to level up.
     */
    private function selectClassToLevel(array $classLevels, bool $chaosMode, CharacterRandomizer $randomizer): string
    {
        if (! $chaosMode || count($classLevels) === 1) {
            // Linear mode or single class: level the primary/only class
            return array_key_first($classLevels);
        }

        // Chaos mode with multiclass: random selection
        $slugs = array_keys($classLevels);

        return $slugs[$randomizer->randomInt(0, count($slugs) - 1)];
    }

    /**
     * Try to add a multiclass.
     *
     * @return string|null Slug of added class, or null if not added
     */
    private function tryAddMulticlass(int $characterId, array $classLevels, CharacterRandomizer $randomizer): ?string
    {
        // Get all available classes
        $allClasses = CharacterClass::whereNull('parent_class_id')
            ->whereNotIn('slug', array_keys($classLevels))
            ->pluck('slug')
            ->toArray();

        if (empty($allClasses)) {
            return null;
        }

        // Pick a random class
        $newClassSlug = $allClasses[$randomizer->randomInt(0, count($allClasses) - 1)];

        // Try to add it
        $response = $this->makeRequest('POST', "/api/v1/characters/{$characterId}/classes", [
            'class_slug' => $newClassSlug,
            'force' => false, // Respect prerequisites
        ]);

        if (isset($response['error'])) {
            // Couldn't add multiclass (probably prerequisites)
            return null;
        }

        return $newClassSlug;
    }

    /**
     * Generate a realistic multiclass plan.
     *
     * Distribution: 60% single class, 30% dual class, 10% triple class.
     * Multiclass levels are typically early (levels 2-5 for realistic feel).
     *
     * @return array{class_count: int, multiclass_levels: array<int>}|null
     */
    private function generateRealisticPlan(
        array $currentClasses,
        int $targetLevel,
        int $currentLevel,
        CharacterRandomizer $randomizer
    ): ?array {
        // Roll for multiclass distribution: 1-60 = single, 61-90 = dual, 91-100 = triple
        $roll = $randomizer->randomInt(1, 100);

        if ($roll <= 60) {
            // Single class - no multiclassing
            return ['class_count' => 1, 'multiclass_levels' => []];
        }

        $levelsRemaining = $targetLevel - $currentLevel;
        if ($levelsRemaining < 2) {
            // Not enough levels to multiclass meaningfully
            return ['class_count' => 1, 'multiclass_levels' => []];
        }

        $multiclassLevels = [];

        if ($roll <= 90) {
            // Dual class (30% chance) - pick one level to add second class
            // Typically early: level 2-5 if available
            $earliestMulticlass = max($currentLevel + 1, 2);
            $latestMulticlass = min($currentLevel + 4, $targetLevel - 1);

            if ($earliestMulticlass <= $latestMulticlass) {
                $multiclassLevels[] = $randomizer->randomInt($earliestMulticlass, $latestMulticlass);
            }

            return ['class_count' => 2, 'multiclass_levels' => $multiclassLevels];
        }

        // Triple class (10% chance) - pick two levels to add classes
        // First multiclass early (level 2-5), second later (level 6-10)
        $earliestFirst = max($currentLevel + 1, 2);
        $latestFirst = min($currentLevel + 4, $targetLevel - 2);

        if ($earliestFirst <= $latestFirst) {
            $firstMulticlass = $randomizer->randomInt($earliestFirst, $latestFirst);
            $multiclassLevels[] = $firstMulticlass;

            // Second multiclass after the first, typically levels 6-10
            $earliestSecond = max($firstMulticlass + 1, 6);
            $latestSecond = min($currentLevel + 9, $targetLevel - 1);

            if ($earliestSecond <= $latestSecond) {
                $multiclassLevels[] = $randomizer->randomInt($earliestSecond, $latestSecond);
            }
        }

        return ['class_count' => 3, 'multiclass_levels' => $multiclassLevels];
    }

    /**
     * Resolve all pending required choices and ASI choices.
     *
     * ASI choices are technically optional (players can delay them), but for
     * automated testing/fixture generation we want to resolve them immediately.
     */
    private function resolveAllPendingChoices(int $characterId, CharacterRandomizer $randomizer): int
    {
        $resolved = 0;
        $maxIterations = 20;

        for ($i = 0; $i < $maxIterations; $i++) {
            $choicesResponse = $this->makeRequest('GET', "/api/v1/characters/{$characterId}/pending-choices");
            $allChoices = $choicesResponse['data']['choices'] ?? [];

            // Filter to:
            // 1. Required choices with remaining > 0
            // 2. ASI choices (which are technically optional but we want to resolve them)
            $pending = array_filter($allChoices, function ($c) {
                $hasRemaining = ($c['remaining'] ?? 0) > 0;
                $isRequired = ($c['required'] ?? false) === true;
                $isAsi = ($c['type'] ?? '') === 'asi_or_feat';

                return $hasRemaining && ($isRequired || $isAsi);
            });

            if (empty($pending)) {
                break;
            }

            foreach ($pending as $choice) {
                $choiceResolved = $this->resolveChoice($characterId, $choice, $randomizer);
                if ($choiceResolved) {
                    $resolved++;
                }
            }
        }

        return $resolved;
    }

    /**
     * Resolve a single pending choice.
     */
    private function resolveChoice(int $characterId, array $choice, CharacterRandomizer $randomizer): bool
    {
        $choiceId = $choice['id'];
        $choiceType = $choice['type'] ?? '';
        $options = $choice['options'] ?? [];
        $metadata = $choice['metadata'] ?? [];
        $count = $choice['remaining'] ?? 1;

        // Fetch options from endpoint if not inline
        if (empty($options) && ! empty($choice['options_endpoint'])) {
            $endpoint = $choice['options_endpoint'];

            // For spell choices, append class parameter if available in metadata
            // This is needed when multiclassing into a spellcasting class, as the
            // available-spells endpoint defaults to the primary class's spell list
            if (in_array($choiceType, ['spell', 'spells_known', 'cantrip'], true)) {
                $classSlug = $metadata['class_slug'] ?? null;
                if ($classSlug && ! str_contains($endpoint, 'class=')) {
                    $separator = str_contains($endpoint, '?') ? '&' : '?';
                    $endpoint .= $separator.'class='.urlencode($classSlug);
                }
            }

            $optionsResponse = $this->makeRequest('GET', $endpoint);
            $options = $optionsResponse['data'] ?? [];
        }

        // ASI choices can work without fetched options if metadata has ability_scores
        $hasAsiMetadata = $choiceType === 'asi_or_feat' && ! empty($metadata['ability_scores']);

        if (empty($options) && ! $hasAsiMetadata) {
            Log::warning('Level-up flow: empty options for choice', [
                'character_id' => $characterId,
                'choice_id' => $choiceId,
                'choice_type' => $choiceType,
                'choice_label' => $choice['label'] ?? 'unknown',
                'options_endpoint' => $choice['options_endpoint'] ?? 'none',
                'metadata' => $metadata,
            ]);

            return false;
        }

        // Select based on type
        $selected = match ($choiceType) {
            'hit_points' => $this->selectHpChoice($options, $randomizer),
            'asi_or_feat' => $this->selectAsiChoice($options, $metadata, $randomizer),
            'subclass' => $this->selectSubclass($options, $randomizer),
            'spell', 'spells_known', 'cantrip' => $this->selectSpells($options, $randomizer, $count),
            'feat' => $this->selectFeat($options, $randomizer),
            'proficiency', 'expertise' => $this->selectProficiency($options, $randomizer, $count),
            'language' => $this->selectLanguage($options, $randomizer, $count),
            'ability_score' => $this->selectAbilityScore($options, $randomizer, $count),
            'optional_feature' => $this->selectOptionalFeature($options, $randomizer, $count),
            'size' => $this->selectGeneric($options, $randomizer, $count),
            default => $this->selectGeneric($options, $randomizer, $count),
        };

        if (empty($selected)) {
            return false;
        }

        // ASI choices use a different payload format (type, increases/feat_slug at root level)
        if ($choiceType === 'asi_or_feat') {
            $payload = $selected; // Already in correct format from selectAsiChoice
        } else {
            $payload = ['selected' => (array) $selected];
        }

        // Subclass choices may have variant_choices (e.g., Totem Warrior totem_spirit, Circle of the Land terrain)
        if ($choiceType === 'subclass') {
            $selectedSlug = $selected[0] ?? null;
            $foundOption = null;
            foreach ($options as $opt) {
                if (($opt['slug'] ?? '') === $selectedSlug) {
                    $foundOption = $opt;
                    break;
                }
            }

            if ($foundOption && ! empty($foundOption['variant_choices'])) {
                $variantSelections = [];
                foreach ($foundOption['variant_choices'] as $choiceGroup => $choiceData) {
                    $variantOptions = $choiceData['options'] ?? [];
                    if (! empty($variantOptions)) {
                        $variantValues = array_column($variantOptions, 'value');
                        $pickedVariant = $randomizer->pickRandom($variantValues, 1);
                        if (! empty($pickedVariant)) {
                            $variantSelections[$choiceGroup] = $pickedVariant[0];
                        }
                    }
                }
                if (! empty($variantSelections)) {
                    $payload['variant_choices'] = $variantSelections;
                }
            }
        }

        $response = $this->makeRequest('POST', "/api/v1/characters/{$characterId}/choices/{$choiceId}", $payload);

        if (isset($response['error'])) {
            Log::warning('Level-up flow: choice resolution failed', [
                'character_id' => $characterId,
                'choice_id' => $choiceId,
                'choice_type' => $choiceType,
                'choice_label' => $choice['label'] ?? 'unknown',
                'payload' => $payload,
                'error' => $response['message'] ?? 'unknown',
                'errors' => $response['errors'] ?? [],
            ]);

            return false;
        }

        return true;
    }

    private function selectHpChoice(array $options, CharacterRandomizer $randomizer): array
    {
        // HP options use 'id' field with values: 'roll', 'average', 'manual'
        // Filter nulls after array_column to handle sparse/null fields correctly
        $values = array_filter(array_column($options, 'id'));
        if (empty($values)) {
            $values = array_filter(array_column($options, 'value'));
        }
        if (empty($values)) {
            $values = array_filter(array_column($options, 'slug'));
        }

        // Prefer 'average' for predictability in testing
        if (in_array('average', $values, true)) {
            return ['average'];
        }

        return $randomizer->pickRandom($values, 1);
    }

    /**
     * Select ASI choice - either ability score increase or feat.
     *
     * @param  array  $options  Available feats from the options endpoint
     * @param  array  $metadata  Choice metadata containing ability_scores and asi_points
     * @param  CharacterRandomizer  $randomizer  For random selections
     * @return array Selection in format: ['type' => 'asi', 'increases' => [...]] or ['type' => 'feat', 'feat_slug' => '...']
     */
    private function selectAsiChoice(array $options, array $metadata, CharacterRandomizer $randomizer): array
    {
        // Extract feat slugs safely from options
        $featSlugs = array_filter(array_column($options, 'slug'));
        $hasFeatOption = ! empty($featSlugs);

        $abilityScores = $metadata['ability_scores'] ?? [];
        $asiPoints = $metadata['asi_points'] ?? 2;

        // If no ability scores available, must pick feat
        if (empty($abilityScores) && $hasFeatOption) {
            $featSlugs = array_values($featSlugs); // Re-index

            return ['type' => 'feat', 'feat_slug' => $featSlugs[$randomizer->randomInt(0, count($featSlugs) - 1)]];
        }

        // If no feats available (or no valid slugs), must pick ASI
        if (! $hasFeatOption) {
            return $this->buildAsiSelection($abilityScores, $asiPoints, $randomizer);
        }

        // Both options available - randomly choose (70% ASI, 30% feat)
        $roll = $randomizer->randomInt(1, 100);
        if ($roll <= 70) {
            return $this->buildAsiSelection($abilityScores, $asiPoints, $randomizer);
        }

        // Pick a feat
        $featSlugs = array_values($featSlugs); // Re-index

        return ['type' => 'feat', 'feat_slug' => $featSlugs[$randomizer->randomInt(0, count($featSlugs) - 1)]];
    }

    /**
     * Build an ASI selection distributing points across ability scores.
     *
     * @param  array  $abilityScores  Available ability scores with current values
     * @param  int  $asiPoints  Points to distribute (typically 2)
     * @param  CharacterRandomizer  $randomizer  For random selections
     * @return array Selection in format: ['type' => 'asi', 'increases' => ['STR' => 2]]
     */
    private function buildAsiSelection(array $abilityScores, int $asiPoints, CharacterRandomizer $randomizer): array
    {
        $maxScore = 20;
        $increases = [];

        // Filter to scores that can be increased (below max)
        $eligibleScores = array_filter($abilityScores, fn ($as) => ($as['current_value'] ?? 10) < $maxScore);

        if (empty($eligibleScores)) {
            // All scores at max - return empty increases (edge case)
            return ['type' => 'asi', 'increases' => []];
        }

        // Re-index for random selection
        $eligibleScores = array_values($eligibleScores);
        $remainingPoints = $asiPoints;

        // Decide distribution: 50% chance +2 to one, 50% chance +1 to two
        $distribution = $randomizer->randomInt(1, 100) <= 50 ? 'single' : 'split';

        if ($distribution === 'single' || count($eligibleScores) === 1) {
            // +2 to one ability score
            $selected = $eligibleScores[$randomizer->randomInt(0, count($eligibleScores) - 1)];
            $code = $selected['code'];
            $currentValue = $selected['current_value'] ?? 10;

            // Cap at 20
            $increase = min($remainingPoints, $maxScore - $currentValue);
            if ($increase > 0) {
                $increases[$code] = $increase;
            }
        } else {
            // +1 to two ability scores
            $firstIndex = $randomizer->randomInt(0, count($eligibleScores) - 1);
            $first = $eligibleScores[$firstIndex];
            $increases[$first['code']] = 1;

            // Pick second (different from first)
            $otherScores = array_filter($eligibleScores, fn ($as, $idx) => $idx !== $firstIndex && ($as['current_value'] ?? 10) < $maxScore, ARRAY_FILTER_USE_BOTH);

            if (! empty($otherScores)) {
                $otherScores = array_values($otherScores);
                $second = $otherScores[$randomizer->randomInt(0, count($otherScores) - 1)];
                $increases[$second['code']] = 1;
            } else {
                // Can't split - just add +2 to first
                $increases[$first['code']] = 2;
            }
        }

        return ['type' => 'asi', 'increases' => $increases];
    }

    private function selectSubclass(array $options, CharacterRandomizer $randomizer): array
    {
        $slugs = array_column($options, 'slug');

        // Use forced subclass if provided and available
        if ($this->forceSubclass && in_array($this->forceSubclass, $slugs, true)) {
            return [$this->forceSubclass];
        }

        return $randomizer->pickRandom(array_filter($slugs), 1);
    }

    private function selectSpells(array $options, CharacterRandomizer $randomizer, int $count): array
    {
        $slugs = array_column($options, 'slug');
        $filteredSlugs = array_filter($slugs);
        $selectCount = min($count, count($filteredSlugs));

        return $randomizer->pickRandom($filteredSlugs, $selectCount);
    }

    private function selectFeat(array $options, CharacterRandomizer $randomizer): array
    {
        $slugs = array_column($options, 'slug');

        return $randomizer->pickRandom(array_filter($slugs), 1);
    }

    private function selectProficiency(array $options, CharacterRandomizer $randomizer, int $count): array
    {
        $slugs = array_column($options, 'slug');

        return $randomizer->pickRandom(array_filter($slugs), min($count, count($slugs)));
    }

    private function selectLanguage(array $options, CharacterRandomizer $randomizer, int $count): array
    {
        $slugs = array_column($options, 'slug');

        return $randomizer->pickRandom(array_filter($slugs), min($count, count($slugs)));
    }

    private function selectAbilityScore(array $options, CharacterRandomizer $randomizer, int $count): array
    {
        $values = array_column($options, 'code');
        if (empty(array_filter($values))) {
            $values = array_column($options, 'value');
        }
        if (empty(array_filter($values))) {
            $values = array_column($options, 'slug');
        }

        return $randomizer->pickRandom(array_filter(array_map('strval', $values)), min($count, count($values)));
    }

    private function selectOptionalFeature(array $options, CharacterRandomizer $randomizer, int $count): array
    {
        $slugs = array_column($options, 'slug');

        return $randomizer->pickRandom(array_filter($slugs), min($count, count($slugs)));
    }

    private function selectGeneric(array $options, CharacterRandomizer $randomizer, int $count): array
    {
        $values = array_column($options, 'slug');
        if (empty(array_filter($values))) {
            $values = array_column($options, 'value');
        }
        if (empty(array_filter($values))) {
            $values = array_column($options, 'id');
        }

        return $randomizer->pickRandom(array_filter(array_map('strval', $values)), min($count, count($values)));
    }

    /**
     * Make an internal request to the Laravel application.
     */
    private function makeRequest(string $method, string $uri, array $data = []): array
    {
        $request = Request::create(
            $uri,
            $method,
            $method === 'GET' ? $data : [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            $method !== 'GET' ? json_encode($data) : null
        );

        $response = app()->handle($request);
        $content = $response->getContent();
        $statusCode = $response->getStatusCode();

        $decoded = json_decode($content, true) ?? [];

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
}
