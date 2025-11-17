<?php

use Illuminate\Support\Facades\Route;

// Temporary test route for CORS testing
Route::get('/test', function () {
    return response()->json(['message' => 'CORS test']);
});

// API routes will be added here
