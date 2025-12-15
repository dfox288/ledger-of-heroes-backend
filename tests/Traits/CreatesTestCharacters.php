<?php

declare(strict_types=1);

namespace Tests\Traits;

use App\Models\Character;
use App\Services\LevelUpFlowTesting\LevelUpFlowExecutor;
use App\Services\WizardFlowTesting\CharacterRandomizer;
use App\Services\WizardFlowTesting\FlowExecutor;
use App\Services\WizardFlowTesting\FlowGenerator;

/**
 * Helper trait for creating fully-formed test characters.
 *
 * Creates characters through the wizard flow (ensuring all choices are made)
 * and levels them up via the level-up flow testing infrastructure.
 */
trait CreatesTestCharacters
{
    /**
     * Create a character through wizard flow and optionally level up.
     *
     * @param  string  $classSlug  Required class slug (e.g., 'phb:warlock')
     * @param  string|null  $subclassSlug  Optional subclass to force
     * @param  int  $targetLevel  Target level (default 1)
     * @param  int|null  $seed  Optional seed for reproducibility
     * @return Character The created and leveled character
     */
    protected function createAndLevelCharacter(
        string $classSlug,
        ?string $subclassSlug = null,
        int $targetLevel = 1,
        ?int $seed = null
    ): Character {
        $seed = $seed ?? random_int(1, 999999);
        $randomizer = new CharacterRandomizer($seed);

        // Create character through wizard flow
        $character = $this->createCharacterViaWizardFlow($classSlug, $subclassSlug, $randomizer);

        // Track for cleanup (if the test has this property)
        if (property_exists($this, 'createdCharacters') && is_array($this->createdCharacters)) {
            $this->createdCharacters[] = $character->id;
        }

        // Level up if target > 1
        if ($targetLevel > 1) {
            $this->levelUpCharacter($character, $targetLevel, $randomizer);
        }

        return $character->fresh();
    }

    /**
     * Create a character via the wizard flow.
     */
    protected function createCharacterViaWizardFlow(
        string $classSlug,
        ?string $subclassSlug,
        CharacterRandomizer $randomizer
    ): Character {
        $generator = new FlowGenerator;
        $executor = new FlowExecutor;

        // Generate linear flow and force class/subclass
        $flow = $generator->linear();

        foreach ($flow as &$step) {
            if ($step['action'] === 'set_class') {
                $step['force_class'] = $classSlug;
            }
            if ($step['action'] === 'set_subclass' && $subclassSlug) {
                $step['force_subclass'] = $subclassSlug;
            }
        }

        $result = $executor->execute($flow, $randomizer);

        if (! $result->isPassed()) {
            throw new \RuntimeException(
                "Wizard flow failed for {$classSlug}: {$result->getSummary()}"
            );
        }

        $character = Character::findOrFail($result->getCharacterId());

        // Verify character is complete
        if (! $character->is_complete) {
            throw new \RuntimeException(
                "Character created but not complete for {$classSlug}. ".
                'Pending choices may not have been resolved.'
            );
        }

        return $character;
    }

    /**
     * Level up a character to the target level.
     */
    protected function levelUpCharacter(
        Character $character,
        int $targetLevel,
        CharacterRandomizer $randomizer
    ): void {
        $executor = new LevelUpFlowExecutor;

        $result = $executor->execute(
            characterId: $character->id,
            targetLevel: $targetLevel,
            randomizer: $randomizer,
            iteration: 1,
            mode: 'linear'
        );

        // Check for errors
        if ($result->getError() !== null) {
            $error = $result->getError();
            throw new \RuntimeException(
                "Level-up failed at level {$error['at_level']} for {$character->public_id}: {$error['message']}"
            );
        }

        // Check all steps passed
        $failedSteps = array_filter(
            $result->getSteps(),
            fn ($step) => $step['status'] !== 'PASS'
        );

        if (! empty($failedSteps)) {
            $firstFailed = reset($failedSteps);
            throw new \RuntimeException(
                "Level-up step failed at level {$firstFailed['level']} for {$character->public_id}: ".
                implode(', ', $firstFailed['errors'] ?? ['Unknown error'])
            );
        }
    }

    /**
     * Count optional features of a specific type for a character.
     */
    protected function countOptionalFeatures(Character $character, string $featureType): int
    {
        return $character->featureSelections()
            ->with('optionalFeature')
            ->get()
            ->filter(fn ($fs) => $fs->optionalFeature !== null)
            ->filter(fn ($fs) => $fs->optionalFeature->feature_type?->value === $featureType)
            ->count();
    }

    /**
     * Get expected optional feature count from config.
     */
    protected function getExpectedFeatureCount(string $featureType, int $level): ?int
    {
        $config = config("dnd-rules.optional_features.{$featureType}.progression", []);

        // Find the highest level in progression that is <= current level
        $count = null;
        foreach ($config as $configLevel => $configCount) {
            if ($configLevel <= $level) {
                $count = $configCount;
            }
        }

        return $count;
    }

    /**
     * Assert character has expected number of optional features.
     */
    protected function assertOptionalFeatureCount(
        Character $character,
        string $featureType,
        int $expectedCount,
        ?string $message = null
    ): void {
        $actualCount = $this->countOptionalFeatures($character, $featureType);

        $message = $message ?? sprintf(
            'Expected %d %s features but found %d for %s at level %d',
            $expectedCount,
            $featureType,
            $actualCount,
            $character->public_id,
            $character->total_level
        );

        expect($actualCount)->toBe($expectedCount, $message);
    }
}
