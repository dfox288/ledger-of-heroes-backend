<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Abstract BaseModel
 *
 * Base class for all D&D Compendium models providing:
 * - Disabled timestamps by default (static reference data)
 * - HasFactory trait for test data generation
 *
 * All models in this application should extend BaseModel instead of Model.
 */
abstract class BaseModel extends Model
{
    use HasFactory;

    /**
     * Indicates if the model should be timestamped.
     *
     * D&D reference data is static and doesn't need created_at/updated_at timestamps.
     *
     * @var bool
     */
    public $timestamps = false;
}
