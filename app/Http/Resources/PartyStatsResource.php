<?php

namespace App\Http\Resources;

use App\Models\Party;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * Resource for party stats endpoint - provides DM dashboard data.
 *
 * Encapsulates the party, characters with full stats, and party-wide summary
 * aggregations needed for a DM screen.
 *
 * @mixin Party
 */
class PartyStatsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'party' => [
                'id' => $this->id,
                'name' => $this->name,
            ],
            'characters' => PartyCharacterStatsResource::collection($this->characters),
            'party_summary' => $this->calculatePartySummary($this->characters),
        ];
    }

    /**
     * Calculate party-wide summary aggregations for DM reference.
     */
    private function calculatePartySummary(Collection $characters): array
    {
        // Healer classes (can be expanded)
        $healerClasses = ['cleric', 'druid', 'paladin', 'bard'];

        // Utility spell base names to check for (matches any prefix like phb:, xge:, etc.)
        $utilitySpellNames = [
            'detect_magic' => 'detect-magic',
            'dispel_magic' => 'dispel-magic',
            'counterspell' => 'counterspell',
        ];

        // Aggregate all languages
        $allLanguages = $characters
            ->flatMap(fn ($char) => $char->languages->map(fn ($cl) => $cl->language?->name))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        // Darkvision tracking
        $darkvisionCount = 0;
        $noDarkvision = [];

        foreach ($characters as $character) {
            $hasDarkvision = false;

            if ($character->race && $character->race->relationLoaded('senses')) {
                $hasDarkvision = $character->race->senses->contains(function ($entitySense) {
                    return $entitySense->sense?->slug === 'core:darkvision';
                });
            }

            if ($hasDarkvision) {
                $darkvisionCount++;
            } else {
                $noDarkvision[] = $character->name;
            }
        }

        // Healer tracking
        $healers = [];
        foreach ($characters as $character) {
            $primaryClass = $character->characterClasses->firstWhere('is_primary', true)?->characterClass;
            if ($primaryClass) {
                $classSlug = $primaryClass->slug ?? '';
                $className = $primaryClass->name ?? '';

                // Check if class slug contains any healer class name
                foreach ($healerClasses as $healerClass) {
                    if (str_contains(strtolower($classSlug), $healerClass)) {
                        $healers[] = "{$character->name} ({$className})";
                        break;
                    }
                }
            }
        }

        // Utility spell tracking - check if any party spell ends with the base name
        $partySpellSlugs = $characters
            ->flatMap(fn ($char) => $char->spells->map(fn ($cs) => $cs->spell_slug))
            ->filter()
            ->unique()
            ->all();

        $hasSpell = fn (string $baseName) => collect($partySpellSlugs)
            ->contains(fn ($slug) => str_ends_with($slug, $baseName));

        $hasDetectMagic = $hasSpell($utilitySpellNames['detect_magic']);
        $hasDispelMagic = $hasSpell($utilitySpellNames['dispel_magic']);
        $hasCounterspell = $hasSpell($utilitySpellNames['counterspell']);

        return [
            'all_languages' => $allLanguages,
            'darkvision_count' => $darkvisionCount,
            'no_darkvision' => $noDarkvision,
            'has_healer' => count($healers) > 0,
            'healers' => $healers,
            'has_detect_magic' => $hasDetectMagic,
            'has_dispel_magic' => $hasDispelMagic,
            'has_counterspell' => $hasCounterspell,
        ];
    }
}
