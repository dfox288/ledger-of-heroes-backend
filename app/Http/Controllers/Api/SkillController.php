<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Skill;

class SkillController extends Controller
{
    public function index()
    {
        return Skill::all();
    }

    public function show(Skill $skill)
    {
        return $skill;
    }
}
