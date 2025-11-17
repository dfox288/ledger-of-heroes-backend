<?php

namespace Tests\Unit\Migrations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SpellEffectsMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_spell_effects_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('spell_effects'));
        $this->assertTrue(Schema::hasColumns('spell_effects', [
            'id', 'spell_id', 'effect_type', 'dice_formula', 'scaling_type',
            'scaling_trigger', 'damage_type_id', 'created_at', 'updated_at'
        ]));
    }
}
