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
            'choice_group' => $this->choiceGroup,
            'choice_count' => $first->choice_count,
            'max_level' => $first->max_level,
            'is_ritual_only' => $first->is_ritual_only,
            'allowed_schools' => $this->getAllowedSchools(),
            'allowed_class' => $this->getAllowedClass($first),
        ];
    }

    /**
     * Get the allowed schools from the choice group.
     *
     * @return array<int, array<string, mixed>>
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
