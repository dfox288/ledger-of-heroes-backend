<?php

namespace Tests\Feature\Migrations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EntitySpellsTableTest extends TestCase
{
    use RefreshDatabase;

    public function test_entity_spells_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('entity_spells'));
    }

    public function test_entity_spells_table_has_all_required_columns(): void
    {
        $columns = [
            'id',
            'reference_type',
            'reference_id',
            'spell_id',
            'ability_score_id',
            'level_requirement',
            'usage_limit',
            'is_cantrip',
            'created_at',
            'updated_at',
        ];

        foreach ($columns as $column) {
            $this->assertTrue(
                Schema::hasColumn('entity_spells', $column),
                "Missing column: {$column}"
            );
        }
    }

    public function test_entity_spells_has_foreign_keys(): void
    {
        // This test verifies the table structure allows foreign keys
        $spell = \App\Models\Spell::factory()->create();
        $race = \App\Models\Race::factory()->create();

        $entitySpell = \App\Models\EntitySpell::create([
            'reference_type' => \App\Models\Race::class,
            'reference_id' => $race->id,
            'spell_id' => $spell->id,
            'is_cantrip' => true,
        ]);

        $this->assertDatabaseHas('entity_spells', [
            'spell_id' => $spell->id,
            'reference_id' => $race->id,
        ]);
    }
}
