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
        $query = Feat::with(['sources.source']);

        // Apply search filter
        if ($request->has('search')) {
            $query->search($request->search);
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
        ]);

        return new FeatResource($feat);
    }
}
