<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ItemProperty;

class ItemPropertyController extends Controller
{
    public function index()
    {
        return ItemProperty::all();
    }

    public function show(ItemProperty $itemProperty)
    {
        return $itemProperty;
    }
}
