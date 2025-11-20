<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LanguageIndexRequest;
use App\Http\Resources\LanguageResource;
use App\Models\Language;

class LanguageController extends Controller
{
    public function index(LanguageIndexRequest $request)
    {
        $query = Language::query();

        // Add search support
        if ($request->has('search')) {
            $search = $request->validated('search');
            $query->where('name', 'LIKE', "%{$search}%");
        }

        // Add pagination support
        $perPage = $request->validated('per_page', 50); // Higher default for lookups
        $entities = $query->paginate($perPage);

        return LanguageResource::collection($entities);
    }

    public function show(Language $language)
    {
        return new LanguageResource($language);
    }
}
