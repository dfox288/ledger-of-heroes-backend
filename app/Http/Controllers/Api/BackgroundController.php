<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BackgroundResource;
use App\Models\Background;
use Illuminate\Http\Request;

class BackgroundController extends Controller
{
    public function index(Request $request)
    {
        $query = Background::with(['sources.source']);

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
        $backgrounds = $query->paginate($perPage);

        return BackgroundResource::collection($backgrounds);
    }

    public function show(Background $background)
    {
        $background->load([
            'sources.source',
            'traits.randomTables.entries',
            'proficiencies.skill.abilityScore',
        ]);

        return new BackgroundResource($background);
    }
}
