<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RaceResource;
use App\Models\Race;
use Illuminate\Http\Request;

class RaceController extends Controller
{
    public function index(Request $request)
    {
        $query = Race::with(['size', 'sources.source']);

        // Apply search filter
        if ($request->has('search')) {
            $query->search($request->search);
        }

        // Apply size filter
        if ($request->has('size')) {
            $query->size($request->size);
        }

        // Apply sorting
        $sortBy = $request->get('sort_by', 'name');
        $sortDirection = $request->get('sort_direction', 'asc');
        $query->orderBy($sortBy, $sortDirection);

        // Paginate
        $perPage = $request->get('per_page', 15);
        $races = $query->paginate($perPage);

        return RaceResource::collection($races);
    }

    public function show(Race $race)
    {
        $race->load([
            'size',
            'sources.source',
            'parent',
            'subraces',
            'proficiencies.skill.abilityScore',
            'traits.randomTables.entries', // Load random tables through traits
            'modifiers.abilityScore',
        ]);

        return new RaceResource($race);
    }
}
