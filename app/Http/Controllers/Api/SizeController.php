<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Size;

class SizeController extends Controller
{
    public function index()
    {
        return Size::all();
    }

    public function show(Size $size)
    {
        return $size;
    }
}
