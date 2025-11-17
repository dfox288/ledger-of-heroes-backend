<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Source;

class SourceController extends Controller
{
    public function index()
    {
        return Source::all();
    }

    public function show(Source $source)
    {
        return $source;
    }
}
