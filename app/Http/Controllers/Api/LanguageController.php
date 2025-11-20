<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LanguageIndexRequest;
use App\Http\Resources\LanguageResource;
use App\Models\Language;

class LanguageController extends Controller
{
    /**
     * List all D&D languages
     *
     * Returns a paginated list of languages in D&D 5e including Common, Elvish, Dwarvish,
     * and exotic languages. Includes script information, language type, and rarity.
     */
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

    /**
     * Get a single language
     *
     * Returns detailed information about a specific D&D language including its script,
     * type (standard/exotic), and rarity.
     */
    public function show(Language $language)
    {
        return new LanguageResource($language);
    }
}
