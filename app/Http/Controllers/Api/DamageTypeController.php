<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DamageType;

class DamageTypeController extends Controller
{
    public function index()
    {
        return DamageType::all();
    }

    public function show(DamageType $damageType)
    {
        return $damageType;
    }
}
