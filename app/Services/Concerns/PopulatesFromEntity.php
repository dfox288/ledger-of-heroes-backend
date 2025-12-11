<?php

namespace App\Services\Concerns;

use App\Models\Character;
use App\Models\Feat;
use App\Models\Race;

/**
 * Trait for services that populate character attributes (languages, proficiencies, etc.)
 * from various entity sources (race, background, feats, class).
 *
 * This trait provides the standard pattern of:
 * 1. Check if the character has the entity assigned
 * 2. For races, handle subrace parent inheritance
 * 3. For feats, handle polymorphic feature relationships
 * 4. Delegate to the implementing service's populate method
 */
trait PopulatesFromEntity
{
    /**
     * Populate fixed items from the character's race.
     * For subraces, also populates inherited items from the parent race.
     */
    public function populateFromRace(Character $character): void
    {
        if (! $character->race_slug) {
            return;
        }

        $race = $character->race;

        // For subraces, first populate from parent race
        if ($race->is_subrace && $race->parent) {
            $this->populateFromEntity($character, $race->parent, 'race');
        }

        // Then populate from the race itself
        $this->populateFromEntity($character, $race, 'race');
    }

    /**
     * Populate fixed items from the character's background.
     */
    public function populateFromBackground(Character $character): void
    {
        if (! $character->background_slug) {
            return;
        }

        $this->populateFromEntity($character, $character->background, 'background');
    }

    /**
     * Populate fixed items from the character's feats.
     * Gets feats via the polymorphic features relationship.
     */
    public function populateFromFeats(Character $character): void
    {
        // Get feat slugs from character features (polymorphic)
        $featSlugs = $character->features()
            ->where('feature_type', Feat::class)
            ->whereNotNull('feature_slug')
            ->pluck('feature_slug')
            ->toArray();

        if (empty($featSlugs)) {
            return;
        }

        // Load all feats in one query to avoid N+1
        $feats = Feat::whereIn('slug', $featSlugs)->get();

        foreach ($feats as $feat) {
            $this->populateFromEntity($character, $feat, 'feat');
        }
    }

    /**
     * Populate fixed items from an entity.
     * Must be implemented by the using service to handle entity-specific logic.
     *
     * @param  Character  $character  The character to populate
     * @param  mixed  $entity  The source entity (Race, Background, Feat, etc.)
     * @param  string  $source  The source identifier ('race', 'background', 'feat', etc.)
     */
    abstract protected function populateFromEntity(Character $character, $entity, string $source): void;
}
