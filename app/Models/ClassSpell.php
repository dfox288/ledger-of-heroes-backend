<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassSpell extends Model
{
    use HasFactory;

    protected $table = 'class_spell';

    protected $fillable = [
        'spell_id',
        'class_name',
        'subclass_name',
    ];

    public function spell(): BelongsTo
    {
        return $this->belongsTo(Spell::class);
    }
}
