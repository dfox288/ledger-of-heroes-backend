<?php

namespace Tests\Unit\Models;

use App\Models\Spell;
use Tests\TestCase;

class SpellTest extends TestCase
{
    public function test_spell_belongs_to_spell_school(): void
    {
        $spell = new Spell();
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class, $spell->spellSchool());
    }

    public function test_spell_belongs_to_source(): void
    {
        $spell = new Spell();
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class, $spell->source());
    }

    public function test_spell_does_not_use_timestamps(): void
    {
        $spell = new Spell();
        $this->assertFalse($spell->timestamps);
    }
}
