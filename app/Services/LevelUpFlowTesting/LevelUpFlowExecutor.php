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
     * @param  bool  $chaosMode  Enable random multiclassing
     */
    public function execute(
        int $characterId,
        int $targetLevel,
        CharacterRandomizer $randomizer,
        int $iteration = 1,
        bool $chaosMode = false,
    ): LevelUpFlowResult {
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

        // Level up from current to target
        for ($level = $currentLevel + 1; $level <= $targetLevel; $level++) {
            try {
                // In chaos mode, maybe add a multiclass
                if ($chaosMode && $level > 2 && $randomizer->randomInt(1, 100) <= 20) {
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
                $classToLevel = $this->selectClassToLevel($classLevels, $chaosMode, $randomizer);

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
     * Resolve all pending required choices.
     */
    private function resolveAllPendingChoices(int $characterId, CharacterRandomizer $randomizer): int
    {
        $resolved = 0;
        $maxIterations = 20;

        for ($i = 0; $i < $maxIterations; $i++) {
            $choicesResponse = $this->makeRequest('GET', "/api/v1/characters/{$characterId}/pending-choices");
            $allChoices = $choicesResponse['data']['choices'] ?? [];

            // Filter to required choices with remaining > 0
            $pending = array_filter($allChoices, function ($c) {
                return ($c['required'] ?? false) === true && ($c['remaining'] ?? 0) > 0;
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
        $count = $choice['remaining'] ?? 1;

        // Fetch options from endpoint if not inline
        if (empty($options) && ! empty($choice['options_endpoint'])) {
            $endpoint = $choice['options_endpoint'];

            // For spell choices, append class parameter if available in metadata
            // This is needed when multiclassing into a spellcasting class, as the
            // available-spells endpoint defaults to the primary class's spell list
            if (in_array($choiceType, ['spell', 'spells_known', 'cantrip'], true)) {
                $classSlug = $choice['metadata']['class_slug'] ?? null;
                if ($classSlug && ! str_contains($endpoint, 'class=')) {
                    $separator = str_contains($endpoint, '?') ? '&' : '?';
                    $endpoint .= $separator.'class='.urlencode($classSlug);
                }
            }

            $optionsResponse = $this->makeRequest('GET', $endpoint);
            $options = $optionsResponse['data'] ?? [];
        }

        if (empty($options)) {
            Log::warning('Level-up flow: empty options for choice', [
                'character_id' => $characterId,
                'choice_id' => $choiceId,
                'choice_type' => $choiceType,
                'choice_label' => $choice['label'] ?? 'unknown',
                'options_endpoint' => $choice['options_endpoint'] ?? 'none',
                'metadata' => $choice['metadata'] ?? [],
            ]);

            return false;
        }

        // Select based on type
        $selected = match ($choiceType) {
            'hit_points' => $this->selectHpChoice($options, $randomizer),
            'asi_or_feat' => $this->selectAsiChoice($options, $randomizer),
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

        $response = $this->makeRequest('POST', "/api/v1/characters/{$characterId}/choices/{$choiceId}", [
            'selected' => (array) $selected,
        ]);

        if (isset($response['error'])) {
            Log::warning('Level-up flow: choice resolution failed', [
                'character_id' => $characterId,
                'choice_id' => $choiceId,
                'choice_type' => $choiceType,
                'choice_label' => $choice['label'] ?? 'unknown',
                'selected' => $selected,
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

    private function selectAsiChoice(array $options, CharacterRandomizer $randomizer): array
    {
        // ASI choices might be ability score increases or feats
        $values = array_column($options, 'value');
        if (empty($values)) {
            $values = array_column($options, 'slug');
        }
        if (empty($values)) {
            $values = array_column($options, 'code');
        }

        return $randomizer->pickRandom(array_filter(array_map('strval', $values)), 1);
    }

    private function selectSubclass(array $options, CharacterRandomizer $randomizer): array
    {
        $slugs = array_column($options, 'slug');

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
