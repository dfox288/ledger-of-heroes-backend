<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SpellSchool;

class SpellSchoolController extends Controller
{
    public function index()
    {
        return SpellSchool::all();
    }

    public function show(SpellSchool $spellSchool)
    {
        return $spellSchool;
    }
}
