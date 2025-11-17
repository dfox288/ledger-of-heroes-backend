<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ItemType;

class ItemTypeController extends Controller
{
    public function index()
    {
        return ItemType::all();
    }

    public function show(ItemType $itemType)
    {
        return $itemType;
    }
}
