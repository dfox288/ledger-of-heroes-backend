<?php

namespace Tests\Unit\Migrations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SpellSchoolsMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_spell_schools_table_has_correct_columns(): void
    {
        $this->assertTrue(Schema::hasTable('spell_schools'));
        $this->assertTrue(Schema::hasColumns('spell_schools', [
            'id', 'code', 'name', 'created_at', 'updated_at'
        ]));
    }

    public function test_spell_schools_code_is_unique(): void
    {
        // Try to insert a duplicate code - should fail
        $this->expectException(\Illuminate\Database\QueryException::class);

        DB::table('spell_schools')->insert([
            'code' => 'A', // This code already exists from seed data
            'name' => 'Duplicate School'
        ]);
    }

    public function test_spell_schools_table_has_seed_data(): void
    {
        $count = DB::table('spell_schools')->count();
        $this->assertEquals(8, $count);

        $schools = DB::table('spell_schools')->pluck('name', 'code')->toArray();
        $this->assertEquals('Abjuration', $schools['A']);
        $this->assertEquals('Conjuration', $schools['C']);
        $this->assertEquals('Divination', $schools['D']);
        $this->assertEquals('Enchantment', $schools['E']);
        $this->assertEquals('Evocation', $schools['EV']);
        $this->assertEquals('Illusion', $schools['I']);
        $this->assertEquals('Necromancy', $schools['N']);
        $this->assertEquals('Transmutation', $schools['T']);
    }
}
