<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Pivot model for class_optional_feature table.
 *
 * Laravel convention: table name is alphabetical (class < optional_feature)
 * Model name follows table: ClassOptionalFeature
 */
class ClassOptionalFeature extends Pivot
{
    protected $table = 'class_optional_feature';

    public $incrementing = true;  // We have an id column

    protected $fillable = [
        'class_id',
        'optional_feature_id',
        'subclass_name',
    ];

    /**
     * Get the character class this pivot belongs to.
     */
    public function characterClass(): BelongsTo
    {
        return $this->belongsTo(CharacterClass::class, 'class_id');
    }

    /**
     * Get the optional feature this pivot belongs to.
     */
    public function optionalFeature(): BelongsTo
    {
        return $this->belongsTo(OptionalFeature::class);
    }
}
