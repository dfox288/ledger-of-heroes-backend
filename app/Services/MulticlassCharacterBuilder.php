<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Character;
use App\Models\CharacterClass;
use App\Services\LevelUpFlowTesting\LevelUpFlowExecutor;
use App\Services\WizardFlowTesting\CharacterRandomizer;
use App\Services\WizardFlowTesting\FlowExecutor;
use App\Services\WizardFlowTesting\FlowGenerator;
use InvalidArgumentException;

/**
 * Builds test characters with specific multiclass combinations.
 *
 * This service creates characters with exact class/level specifications
 * for testing multiclass features like spellcasting slots, proficiencies, etc.
 *
 * @example
 * $builder->build([
 *     ['class' => 'phb:wizard', 'level' => 5],
 *     ['class' => 'phb:cleric', 'level' => 5],
 * ], seed: 42);
 */
class MulticlassCharacterBuilder
{
    private const MAX_LEVEL = 20;

    public function __construct(
        private AddClassService $addClassService,
    ) {}

    /**
     * Build a character with specific class levels.
     *
     * @param  array<array{class: string, level: int}>  $classLevels  Array of class specifications
     * @param  int|null  $seed  Random seed for reproducibility
     * @param  bool  $force  Bypass multiclass prerequisites
     * @return Character The created character
     *
     * @throws InvalidArgumentException If total level exceeds 20 or class levels are invalid
     */
    public function build(array $classLevels, ?int $seed = null, bool $force = true): Character
    {
        $this->validateClassLevels($classLevels);

        // Resolve shorthand class slugs
        $classLevels = array_map(function ($spec) {
            return [
                'class' => $this->resolveClassSlug($spec['class']),
                'level' => $spec['level'],
            ];
        }, $classLevels);

        $seed = $seed ?? random_int(1, 999999);
        $randomizer = new CharacterRandomizer($seed);

        // 1. Create base character via wizard flow with first class
        $firstClass = $classLevels[0];
        $character = $this->createViaWizard($firstClass['class'], $randomizer);

        if ($character === null) {
            throw new \RuntimeException('Failed to create base character via wizard flow');
        }

        // 2. Add additional classes (each starts at level 1)
        //    After adding each class, resolve any pending choices to keep character complete
        $additionalClasses = array_slice($classLevels, 1);
        foreach ($additionalClasses as $classSpec) {
            $class = CharacterClass::where('slug', $classSpec['class'])->firstOrFail();
            $this->addClassService->addClass($character, $class, force: $force);

            // Resolve any pending choices from multiclass (e.g., proficiency choices)
            $this->resolveAllPendingChoices($character->id, $randomizer);
        }

        // 3. Level up each class to target level
        //    First class starts at level 1, needs (level - 1) more levels
        //    Additional classes start at level 1 after addClass, need (level - 1) more levels
        $levelUpExecutor = new LevelUpFlowExecutor;

        // Level up first class
        $this->levelClassToTarget($character, $firstClass['class'], $firstClass['level'], $randomizer, $levelUpExecutor);

        // Level up additional classes
        foreach ($additionalClasses as $classSpec) {
            $this->levelClassToTarget($character, $classSpec['class'], $classSpec['level'], $randomizer, $levelUpExecutor);
        }

        return $character->fresh();
    }

    /**
     * Parse a combination string into class level specifications.
     *
     * @param  string  $spec  Combination string like "wizard:5,cleric:5" or "erlw:artificer:5"
     * @return array<array{class: string, level: int}>
     */
    public static function parseClassLevels(string $spec): array
    {
        $result = [];

        foreach (explode(',', $spec) as $classLevel) {
            $parts = explode(':', $classLevel);

            if (count($parts) === 2) {
                // Shorthand: "wizard:5"
                [$class, $level] = $parts;
                $result[] = [
                    'class' => self::resolveClassSlugStatic($class),
                    'level' => (int) $level,
                ];
            } elseif (count($parts) === 3) {
                // Full slug: "erlw:artificer:5"
                $class = "{$parts[0]}:{$parts[1]}";
                $level = (int) $parts[2];
                $result[] = [
                    'class' => $class,
                    'level' => $level,
                ];
            } else {
                throw new InvalidArgumentException("Invalid class:level format: {$classLevel}");
            }
        }

        return $result;
    }

    /**
     * Resolve shorthand class slug to full slug.
     *
     * @param  string  $class  Class slug (shorthand or full)
     * @return string Full class slug
     */
    private function resolveClassSlug(string $class): string
    {
        return self::resolveClassSlugStatic($class);
    }

    /**
     * Static version of resolveClassSlug for use in parseClassLevels.
     */
    private static function resolveClassSlugStatic(string $class): string
    {
        if (str_contains($class, ':')) {
            return $class;
        }

        return "phb:{$class}";
    }

    /**
     * Validate class level specifications.
     *
     * @throws InvalidArgumentException If validation fails
     */
    private function validateClassLevels(array $classLevels): void
    {
        if (empty($classLevels)) {
            throw new InvalidArgumentException('At least one class must be specified');
        }

        $totalLevel = array_sum(array_column($classLevels, 'level'));
        if ($totalLevel > self::MAX_LEVEL) {
            throw new InvalidArgumentException('Total level cannot exceed 20');
        }

        foreach ($classLevels as $spec) {
            if (! isset($spec['class']) || ! isset($spec['level'])) {
                throw new InvalidArgumentException('Each class specification must have "class" and "level" keys');
            }

            if ($spec['level'] < 1) {
                throw new InvalidArgumentException('Class level must be at least 1');
            }
        }
    }

    /**
     * Create a character via wizard flow with the specified class.
     */
    private function createViaWizard(string $classSlug, CharacterRandomizer $randomizer): ?Character
    {
        $generator = new FlowGenerator;
        $executor = new FlowExecutor;

        // Generate linear wizard flow
        $flow = $generator->linear();

        // Force the specified class
        foreach ($flow as &$step) {
            if ($step['action'] === 'set_class') {
                $step['force_class'] = $classSlug;
                break;
            }
        }

        $result = $executor->execute($flow, $randomizer);

        if (! $result->isPassed()) {
            return null;
        }

        return Character::find($result->getCharacterId());
    }

    /**
     * Level up a specific class to the target level.
     */
    private function levelClassToTarget(
        Character $character,
        string $classSlug,
        int $targetLevel,
        CharacterRandomizer $randomizer,
        LevelUpFlowExecutor $executor  // @phpstan-ignore-line Kept for potential future use
    ): void {
        // Use direct level-up approach for control over which class to level
        $this->levelUpClassDirectly($character, $classSlug, $targetLevel, $randomizer);
    }

    /**
     * Level up a class directly using internal API calls.
     */
    private function levelUpClassDirectly(
        Character $character,
        string $classSlug,
        int $targetLevel,
        CharacterRandomizer $randomizer
    ): void {
        // Get current level of this class
        $character->refresh();
        $pivot = $character->characterClasses()->where('class_slug', $classSlug)->first();
        $currentLevel = $pivot?->level ?? 1;

        $levelsToGain = $targetLevel - $currentLevel;

        for ($i = 0; $i < $levelsToGain; $i++) {
            // Level up via API
            $response = $this->makeRequest(
                'POST',
                "/api/v1/characters/{$character->id}/classes/{$classSlug}/level-up"
            );

            if (isset($response['error'])) {
                throw new \RuntimeException("Failed to level up {$classSlug}: ".($response['message'] ?? 'unknown'));
            }

            // Resolve all pending choices
            $this->resolveAllPendingChoices($character->id, $randomizer);
        }
    }

    /**
     * Resolve all pending required choices.
     */
    private function resolveAllPendingChoices(int $characterId, CharacterRandomizer $randomizer): void
    {
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
                $this->resolveChoice($characterId, $choice, $randomizer);
            }
        }
    }

    /**
     * Resolve a single pending choice.
     */
    private function resolveChoice(int $characterId, array $choice, CharacterRandomizer $randomizer): void
    {
        $choiceId = $choice['id'];
        $choiceType = $choice['type'] ?? '';
        $options = $choice['options'] ?? [];
        $count = $choice['remaining'] ?? 1;

        // Fetch options from endpoint if not inline
        if (empty($options) && ! empty($choice['options_endpoint'])) {
            $endpoint = $choice['options_endpoint'];

            // For spell choices, append class parameter if available
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
            return;
        }

        // Select based on type
        $selected = match ($choiceType) {
            'hit_points' => $this->selectHpChoice($options),
            'subclass' => $this->selectSubclass($options, $randomizer),
            'spell', 'spells_known', 'cantrip' => $this->selectSpells($options, $randomizer, $count),
            'proficiency', 'expertise' => $this->selectProficiency($options, $randomizer, $count),
            'language' => $this->selectLanguage($options, $randomizer, $count),
            'ability_score' => $this->selectAbilityScore($options, $randomizer, $count),
            'asi_or_feat' => $this->selectAsiChoice($options, $randomizer),
            default => $this->selectGeneric($options, $randomizer, $count),
        };

        if (empty($selected)) {
            return;
        }

        $this->makeRequest('POST', "/api/v1/characters/{$characterId}/choices/{$choiceId}", [
            'selected' => (array) $selected,
        ]);
    }

    private function selectHpChoice(array $options): array
    {
        // Prefer 'average' for predictability
        $values = array_filter(array_column($options, 'id'));
        if (in_array('average', $values, true)) {
            return ['average'];
        }

        return array_slice($values, 0, 1);
    }

    private function selectAsiChoice(array $options, CharacterRandomizer $randomizer): array
    {
        $values = array_column($options, 'value');
        if (empty($values)) {
            $values = array_column($options, 'slug');
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

        return $randomizer->pickRandom(array_filter($slugs), min($count, count($slugs)));
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

        return $randomizer->pickRandom(array_filter(array_map('strval', $values)), min($count, count($values)));
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
        $request = \Illuminate\Http\Request::create(
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
