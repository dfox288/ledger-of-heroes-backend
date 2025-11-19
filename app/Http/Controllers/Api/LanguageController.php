<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\LanguageResource;
use App\Models\Language;

class LanguageController extends Controller
{
    public function index()
    {
        return LanguageResource::collection(Language::all());
    }

    public function show(Language $language)
    {
        return new LanguageResource($language);
    }
}
