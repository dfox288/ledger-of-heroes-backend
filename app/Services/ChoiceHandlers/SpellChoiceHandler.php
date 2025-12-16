<?php

namespace App\Services\ChoiceHandlers;

use App\DTOs\PendingChoice;
use App\Exceptions\InvalidSelectionException;
use App\Models\Character;
use App\Models\CharacterClassPivot;
use App\Models\CharacterSpell;
use App\Models\ClassFeature;
use App\Models\EntityChoice;
use App\Models\Spell;
use Illuminate\Support\Collection;

class SpellChoiceHandler extends AbstractChoiceHandler
{
    public function getType(): string
    {
        return 'spell';
    }

    public function getChoices(Character $character): Collection
    {
        $choices = collect();

        foreach ($character->characterClasses as $pivot) {
            $class = $pivot->characterClass;

            // Skip non-casters
            if (! $this->isSpellcaster($class)) {
                continue;
            }

            // Get progression for this class at this level
            $progression = $class->levelProgression()
                ->where('level', $pivot->level)
                ->first();

            if (! $progression) {
                continue;
            }

            // Cantrip choices
            if ($progression->cantrips_known > 0) {
                $cantripChoice = $this->getCantripsChoice($character, $pivot, $progression);
                if ($cantripChoice) {
                    $choices->push($cantripChoice);
                }
            }

            // Spell known choices (only for known casters, not prepared casters)
            if ($progression->spells_known > 0) {
                $spellChoice = $this->getSpellsKnownChoice($character, $pivot, $progression);
                if ($spellChoice) {
                    $choices->push($spellChoice);
                }
            }
        }

        // Check subclass feature spell choices (e.g., Nature Domain druid cantrip)
        // Eager load subclass features to avoid N+1 queries
        $character->loadMissing('characterClasses.subclass.features');

        foreach ($character->characterClasses as $pivot) {
            $subclass = $pivot->subclass;
            if (! $subclass) {
                continue;
            }

            $featureIds = $subclass->features->pluck('id');
            if ($featureIds->isEmpty()) {
                continue;
            }

            // Query spell choices from unified entity_choices table
            $spellChoices = EntityChoice::where('reference_type', ClassFeature::class)
                ->whereIn('reference_id', $featureIds)
                ->where('choice_type', 'spell')
                ->get();

            foreach ($spellChoices as $spellChoice) {
                $feature = ClassFeature::find($spellChoice->reference_id);
                if (! $feature) {
                    continue;
                }
                $choice = $this->buildFeatureSpellChoice($character, $pivot, $feature, $spellChoice);
                if ($choice) {
                    $choices->push($choice);
                }
            }
        }

        return $choices;
    }

    public function resolve(Character $character, PendingChoice $choice, array $selection): void
    {
        $selected = $selection['selected'] ?? [];
        if (empty($selected)) {
            throw new InvalidSelectionException($choice->id, 'empty', 'Selection cannot be empty');
        }

        // Validate selection doesn't exceed quantity limit
        if (count($selected) > $choice->quantity) {
            throw new InvalidSelectionException(
                $choice->id,
                'exceeds_limit',
                'Selection of '.count($selected)." exceeds limit of {$choice->quantity}"
            );
        }

        // Validate spell slugs exist
        $spells = Spell::whereIn('slug', $selected)->get();
        if ($spells->count() !== count($selected)) {
            throw new InvalidSelectionException(
                $choice->id,
                'invalid_spell_slugs',
                'One or more spell slugs do not exist'
            );
        }

        $parsed = $this->parseChoiceId($choice->id);

        // Clear existing spells for this choice before adding new ones
        // This ensures re-submitting replaces rather than duplicates
        // Use the group (cantrips vs spells_known) to determine spell level filter
        $group = $parsed['group'] ?? '';
        $isCantrip = in_array($group, ['cantrips', 'feature_cantrip'], true);

        // Get class slug for multiclass support
        $classSlug = in_array($parsed['source'], ['class', 'subclass', 'subclass_feature'], true)
            ? $parsed['sourceSlug']
            : null;

        $query = $character->spells()
            ->where('source', $parsed['source']);

        // Filter by class_slug for multiclass support
        if ($classSlug) {
            $query->where('class_slug', $classSlug);
        }

        // Only filter by level_acquired for class source, not subclass_feature
        if ($parsed['source'] === 'class') {
            $query->where('level_acquired', $parsed['level']);
        }

        $query->whereHas('spell', function ($query) use ($isCantrip) {
            if ($isCantrip) {
                $query->where('level', 0);
            } else {
                $query->where('level', '>', 0);
            }
        })->delete();

        // Create CharacterSpell records
        foreach ($selected as $spellSlug) {
            CharacterSpell::create([
                'character_id' => $character->id,
                'spell_slug' => $spellSlug,
                'source' => $parsed['source'],
                'class_slug' => $classSlug,
                'level_acquired' => $parsed['level'],
                'preparation_status' => 'known',
            ]);
        }

        // Reload spells relationship
        $character->load('spells.spell');
    }

    public function canUndo(Character $character, PendingChoice $choice): bool
    {
        return true; // Spells can be changed during character creation
    }

    public function undo(Character $character, PendingChoice $choice): void
    {
        $parsed = $this->parseChoiceId($choice->id);

        // Delete CharacterSpell records for this specific choice
        // Use the group (cantrips vs spells_known) to determine spell level filter
        $group = $parsed['group'] ?? '';
        $isCantrip = in_array($group, ['cantrips', 'feature_cantrip'], true);

        $query = $character->spells()
            ->where('source', $parsed['source']);

        // Only filter by level_acquired for class source, not subclass_feature
        if ($parsed['source'] === 'class') {
            $query->where('level_acquired', $parsed['level']);
        }

        $query->whereHas('spell', function ($query) use ($isCantrip) {
            if ($isCantrip) {
                $query->where('level', 0);
            } else {
                $query->where('level', '>', 0);
            }
        })->delete();

        $character->load('spells.spell');
    }

    /**
     * Check if class is a spellcaster
     */
    private function isSpellcaster($class): bool
    {
        return $class->spellcasting_ability_id !== null;
    }

    /**
     * Get cantrip choice for a class at a level
     */
    private function getCantripsChoice(
        Character $character,
        CharacterClassPivot $pivot,
        $progression
    ): ?PendingChoice {
        $class = $pivot->characterClass;

        // Count ALL known cantrips for this class (across all levels)
        // cantrips_known in progression is cumulative, not per-level
        $allKnownCantrips = $character->spells()
            ->where('source', 'class')
            ->where('class_slug', $class->slug)
            ->whereHas('spell', fn ($q) => $q->where('level', 0))
            ->get();

        $totalKnown = $allKnownCantrips->count();
        $totalRequired = $progression->cantrips_known;
        $remaining = max(0, $totalRequired - $totalKnown);

        // Get cantrips selected at THIS level for the selected array
        $cantripsAtThisLevel = $character->spells()
            ->where('source', 'class')
            ->where('class_slug', $class->slug)
            ->where('level_acquired', $pivot->level)
            ->whereHas('spell', fn ($q) => $q->where('level', 0))
            ->get();

        $selected = $cantripsAtThisLevel->pluck('spell_slug')->filter()->toArray();

        return new PendingChoice(
            id: $this->generateChoiceId('spell', 'class', $class->slug, $pivot->level, 'cantrips'),
            type: 'spell',
            subtype: 'cantrip',
            source: 'class',
            sourceName: $class->name,
            levelGranted: $pivot->level,
            required: true,
            quantity: $totalRequired, // Total cantrips required at this level
            remaining: $remaining,
            selected: $selected,
            options: null,
            optionsEndpoint: "/api/v1/characters/{$character->id}/available-spells?max_level=0",
            metadata: [
                'spell_level' => 0,
                'class_slug' => $class->slug,
            ],
        );
    }

    /**
     * Get spells known choice for a known caster at a level
     */
    private function getSpellsKnownChoice(
        Character $character,
        CharacterClassPivot $pivot,
        $progression
    ): ?PendingChoice {
        $class = $pivot->characterClass;

        // Count ALL known spells for this class (excluding cantrips, across all levels)
        // spells_known in progression is cumulative, not per-level
        $allKnownSpells = $character->spells()
            ->where('source', 'class')
            ->where('class_slug', $class->slug)
            ->whereHas('spell', fn ($q) => $q->where('level', '>', 0))
            ->get();

        $totalKnown = $allKnownSpells->count();
        $totalRequired = $progression->spells_known;
        $remaining = max(0, $totalRequired - $totalKnown);

        // Get spells selected at THIS level for the selected array
        $spellsAtThisLevel = $character->spells()
            ->where('source', 'class')
            ->where('class_slug', $class->slug)
            ->where('level_acquired', $pivot->level)
            ->whereHas('spell', fn ($q) => $q->where('level', '>', 0))
            ->get();

        $selected = $spellsAtThisLevel->pluck('spell_slug')->filter()->toArray();

        // Determine max spell level for this class level
        $maxSpellLevel = $this->getMaxSpellLevel($class, $pivot->level);

        return new PendingChoice(
            id: $this->generateChoiceId('spell', 'class', $class->slug, $pivot->level, 'spells_known'),
            type: 'spell',
            subtype: 'spells_known',
            source: 'class',
            sourceName: $class->name,
            levelGranted: $pivot->level,
            required: true,
            quantity: $totalRequired, // Total spells required at this level
            remaining: $remaining,
            selected: $selected,
            options: null,
            optionsEndpoint: "/api/v1/characters/{$character->id}/available-spells?min_level=1&max_level={$maxSpellLevel}",
            metadata: [
                'spell_level' => $maxSpellLevel,
                'class_slug' => $class->slug,
            ],
        );
    }

    /**
     * Determine the maximum spell level accessible at this character level
     */
    private function getMaxSpellLevel($class, int $characterLevel): int
    {
        // For known casters at level 1, they can only learn 1st level spells
        // This is a simplified version - could be enhanced with actual spell slot checking
        $progression = $class->levelProgression()
            ->where('level', $characterLevel)
            ->first();

        if (! $progression) {
            return 1;
        }

        // Check spell slot columns to determine max spell level
        $ordinals = ['1st', '2nd', '3rd', '4th', '5th', '6th', '7th', '8th', '9th'];
        $maxLevel = 0;

        for ($i = 1; $i <= 9; $i++) {
            $column = "spell_slots_{$ordinals[$i - 1]}";
            if ($progression->{$column} > 0) {
                $maxLevel = $i;
            }
        }

        return max(1, $maxLevel);
    }

    /**
     * Build a pending choice for a subclass feature spell choice
     */
    private function buildFeatureSpellChoice(
        Character $character,
        CharacterClassPivot $pivot,
        ClassFeature $feature,
        EntityChoice $spellChoice
    ): ?PendingChoice {
        // Get max_level and class constraints from EntityChoice
        $maxLevel = $spellChoice->spell_max_level ?? 0;
        $isCantrip = $maxLevel === 0;
        $quantity = $spellChoice->quantity ?? 1;

        // Get already selected spells for this feature
        $selectedSpells = $character->spells()
            ->where('source', 'subclass_feature')
            ->whereHas('spell', fn ($q) => $isCantrip ? $q->where('level', 0) : $q->where('level', '>', 0))
            ->get();

        $selected = $selectedSpells->pluck('spell_slug')->filter()->toArray();
        $remaining = $quantity - count($selected);

        // Build options endpoint with class filter
        $classSlug = $spellChoice->spell_list_slug;
        $endpoint = "/api/v1/characters/{$character->id}/available-spells?max_level={$maxLevel}";
        if ($classSlug) {
            $endpoint .= "&class={$classSlug}";
        }

        return new PendingChoice(
            id: $this->generateChoiceId('spell', 'subclass_feature', $feature->characterClass->slug, $feature->level, $spellChoice->choice_group),
            type: 'spell',
            subtype: $isCantrip ? 'cantrip' : 'spell',
            source: 'subclass_feature',
            sourceName: $feature->feature_name,
            levelGranted: $spellChoice->level_granted ?? $feature->level,
            required: $spellChoice->is_required ?? true,
            quantity: $quantity,
            remaining: $remaining,
            selected: $selected,
            options: null,
            optionsEndpoint: $endpoint,
            metadata: [
                'spell_level' => $maxLevel,
                'class_slug' => $classSlug,
                'feature_id' => $feature->id,
                'choice_group' => $spellChoice->choice_group,
            ],
        );
    }
}
