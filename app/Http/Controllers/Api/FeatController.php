<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\FeatResource;
use App\Models\Feat;
use Illuminate\Http\Request;

class FeatController extends Controller
{
    public function index(Request $request)
    {
        $query = Feat::with(['sources.source', 'prerequisites.prerequisite']);

        // Apply search filter
        if ($request->has('search')) {
            $query->search($request->search);
        }

        // Filter by prerequisite race
        if ($request->has('prerequisite_race')) {
            $query->wherePrerequisiteRace($request->prerequisite_race);
        }

        // Filter by prerequisite ability score
        if ($request->has('prerequisite_ability')) {
            $minValue = $request->has('min_value') ? (int) $request->min_value : null;
            $query->wherePrerequisiteAbility($request->prerequisite_ability, $minValue);
        }

        // Filter by prerequisite proficiency
        if ($request->has('prerequisite_proficiency')) {
            $query->wherePrerequisiteProficiency($request->prerequisite_proficiency);
        }

        // Filter by presence of prerequisites
        if ($request->has('has_prerequisites')) {
            $query->withOrWithoutPrerequisites($request->boolean('has_prerequisites'));
        }

        // Filter by granted proficiency
        if ($request->has('grants_proficiency')) {
            $query->grantsProficiency($request->grants_proficiency);
        }

        // Filter by granted skill
        if ($request->has('grants_skill')) {
            $query->grantsSkill($request->grants_skill);
        }

        // Apply sorting
        $sortBy = $request->get('sort_by', 'name');
        $sortDirection = $request->get('sort_direction', 'asc');
        $query->orderBy($sortBy, $sortDirection);

        // Paginate
        $perPage = $request->get('per_page', 15);
        $feats = $query->paginate($perPage);

        return FeatResource::collection($feats);
    }

    public function show(Feat $feat)
    {
        $feat->load([
            'sources.source',
            'modifiers.abilityScore',
            'modifiers.skill',
            'proficiencies.skill.abilityScore',
            'proficiencies.proficiencyType',
            'conditions',
            'prerequisites.prerequisite',
        ]);

        return new FeatResource($feat);
    }
}
