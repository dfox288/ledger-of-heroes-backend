<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AbilityScore;

class AbilityScoreController extends Controller
{
    public function index()
    {
        return AbilityScore::all();
    }

    public function show(AbilityScore $abilityScore)
    {
        return $abilityScore;
    }
}
