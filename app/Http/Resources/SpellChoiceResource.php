<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * Resource for grouped spell choices.
 *
 * Takes a collection of EntitySpell models that share the same choice_group
 * and presents them as a single grouped choice with constraints.
 *
 * @property string $choice_group Group identifier (e.g., "spell_choice_1")
 * @property int $choice_count Number of spells to pick from this pool
 * @property int $max_level Maximum spell level (0=cantrip, 1-9=spell level)
 * @property bool $is_ritual_only Whether spells must have ritual tag
 * @property SpellSchoolResource[] $allowed_schools Schools the spell can be from
 * @property CharacterClassResource|null $allowed_class Class spell list to choose from
 */
class SpellChoiceResource extends JsonResource
{
    /**
     * The choice group name.
     */
    private string $choiceGroup;

    /**
     * Create a new resource instance.
     *
     * @param  Collection  $resource  Collection of EntitySpell models for this choice group
     * @param  string  $choiceGroup  The choice group identifier
     */
    public function __construct(Collection $resource, string $choiceGroup)
    {
        parent::__construct($resource);
        $this->choiceGroup = $choiceGroup;
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $first = $this->resource->first();

        return [
            'choice_group' => (string) $this->choiceGroup,
            'choice_count' => (int) $first->choice_count,
            'max_level' => (int) $first->max_level,
            'is_ritual_only' => (bool) $first->is_ritual_only,
            'allowed_schools' => $this->getAllowedSchools(),
            'allowed_class' => $this->getAllowedClass($first),
        ];
    }

    /**
     * Get the allowed schools from the choice group.
     *
     * @return SpellSchoolResource[]
     */
    private function getAllowedSchools(): array
    {
        return $this->resource
            ->filter(fn ($s) => $s->school_id !== null)
            ->map(fn ($s) => new SpellSchoolResource($s->school))
            ->values()
            ->all();
    }

    /**
     * Get the allowed class if set.
     */
    private function getAllowedClass($first): ?CharacterClassResource
    {
        if ($first->class_id && $first->characterClass) {
            return new CharacterClassResource($first->characterClass);
        }

        return null;
    }
}
