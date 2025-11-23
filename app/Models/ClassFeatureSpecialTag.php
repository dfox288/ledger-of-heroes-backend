<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassFeatureSpecialTag extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'class_feature_id',
        'tag',
    ];

    public function classFeature(): BelongsTo
    {
        return $this->belongsTo(ClassFeature::class);
    }
}
