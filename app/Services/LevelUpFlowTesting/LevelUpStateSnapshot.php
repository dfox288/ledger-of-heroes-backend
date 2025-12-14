<?php

declare(strict_types=1);

namespace App\Services\LevelUpFlowTesting;

use App\Services\WizardFlowTesting\StateSnapshot;

/**
 * Extended state snapshot for level-up flow testing.
 *
 * Adds level-up specific derived fields on top of the base StateSnapshot.
 */
class LevelUpStateSnapshot extends StateSnapshot
{
    /**
     * Capture full character state with level-up specific fields.
     */
    public function capture(int|string $characterId): array
    {
        $snapshot = parent::capture($characterId);

        // Add level-up specific derived fields
        $snapshot['level_up_derived'] = $this->deriveLevelUpFields($snapshot);

        return $snapshot;
    }

    /**
     * Derive level-up specific fields for validation.
     */
    public function deriveLevelUpFields(array $snapshot): array
    {
        $character = $snapshot['character']['data'] ?? [];
        $features = $snapshot['features']['data'] ?? [];
        $pendingChoices = $snapshot['pending_choices']['data']['choices'] ?? [];

        return [
            'total_level' => $character['total_level'] ?? 0,
            'max_hp' => $character['max_hit_points'] ?? 0,
            'class_levels' => $this->extractClassLevels($character['classes'] ?? []),
            'subclasses' => $this->extractSubclasses($character['classes'] ?? []),
            'required_pending_count' => $this->countRequiredPending($pendingChoices),
            'ability_score_totals' => $character['ability_scores'] ?? [],
            'feat_slugs' => $this->extractFeatSlugs($features),
        ];
    }

    /**
     * Extract class levels as slug => level map.
     */
    private function extractClassLevels(array $classes): array
    {
        $levels = [];

        foreach ($classes as $classData) {
            $slug = $classData['class']['slug'] ?? null;
            $level = $classData['level'] ?? 0;

            if ($slug !== null) {
                $levels[$slug] = $level;
            }
        }

        return $levels;
    }

    /**
     * Extract subclasses as class_slug => subclass_slug map.
     */
    private function extractSubclasses(array $classes): array
    {
        $subclasses = [];

        foreach ($classes as $classData) {
            $classSlug = $classData['class']['slug'] ?? null;
            $subclassSlug = $classData['subclass']['slug'] ?? null;

            if ($classSlug !== null && $subclassSlug !== null) {
                $subclasses[$classSlug] = $subclassSlug;
            }
        }

        return $subclasses;
    }

    /**
     * Count required pending choices with remaining > 0.
     */
    private function countRequiredPending(array $pendingChoices): int
    {
        return count(array_filter(
            $pendingChoices,
            fn ($c) => ($c['required'] ?? false) === true && ($c['remaining'] ?? 0) > 0
        ));
    }

    /**
     * Extract feat slugs from features.
     */
    private function extractFeatSlugs(array $features): array
    {
        $featSlugs = [];

        foreach ($features as $feature) {
            $source = $feature['source'] ?? '';

            // Feats have source = 'feat'
            if ($source === 'feat') {
                $featSlugs[] = $feature['slug'];
            }
        }

        return $featSlugs;
    }
}
