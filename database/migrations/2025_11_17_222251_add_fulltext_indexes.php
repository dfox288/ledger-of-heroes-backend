<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // FULLTEXT indexes only supported in MySQL
        // Skip for other database drivers (like SQLite in tests)
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        // Add FULLTEXT indexes for searchable fields
        DB::statement('ALTER TABLE spells ADD FULLTEXT INDEX spells_search (name, description)');
        DB::statement('ALTER TABLE items ADD FULLTEXT INDEX items_search (name, description)');
        DB::statement('ALTER TABLE races ADD FULLTEXT INDEX races_search (name, description)');
        DB::statement('ALTER TABLE backgrounds ADD FULLTEXT INDEX backgrounds_search (name, description)');
        DB::statement('ALTER TABLE feats ADD FULLTEXT INDEX feats_search (name, description)');
        DB::statement('ALTER TABLE classes ADD FULLTEXT INDEX classes_search (name, description)');
        DB::statement('ALTER TABLE monsters ADD FULLTEXT INDEX monsters_search (name, description)');
    }

    public function down(): void
    {
        // Skip for non-MySQL databases
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE spells DROP INDEX spells_search');
        DB::statement('ALTER TABLE items DROP INDEX items_search');
        DB::statement('ALTER TABLE races DROP INDEX races_search');
        DB::statement('ALTER TABLE backgrounds DROP INDEX backgrounds_search');
        DB::statement('ALTER TABLE feats DROP INDEX feats_search');
        DB::statement('ALTER TABLE classes DROP INDEX classes_search');
        DB::statement('ALTER TABLE monsters DROP INDEX monsters_search');
    }
};
