<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Spell extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'level',
        'school_id',
        'is_ritual',
        'casting_time',
        'range',
        'duration',
        'has_verbal_component',
        'has_somatic_component',
        'has_material_component',
        'material_description',
        'material_cost_gp',
        'material_consumed',
        'description',
        'source_book_id',
        'source_page',
    ];

    protected $casts = [
        'level' => 'integer',
        'is_ritual' => 'boolean',
        'has_verbal_component' => 'boolean',
        'has_somatic_component' => 'boolean',
        'has_material_component' => 'boolean',
        'material_cost_gp' => 'integer',
        'material_consumed' => 'boolean',
        'source_page' => 'integer',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(SpellSchool::class, 'school_id');
    }

    public function sourceBook(): BelongsTo
    {
        return $this->belongsTo(SourceBook::class, 'source_book_id');
    }

    public function effects(): HasMany
    {
        return $this->hasMany(SpellEffect::class);
    }

    public function classes(): HasMany
    {
        return $this->hasMany(ClassSpell::class);
    }

    public function generateSlug(): void
    {
        $this->slug = Str::slug($this->name);
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($spell) {
            if (empty($spell->slug)) {
                $spell->generateSlug();
            }
        });
    }
}
