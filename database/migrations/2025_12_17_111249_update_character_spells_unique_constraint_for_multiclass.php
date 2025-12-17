<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #735: Allow same spell to exist for multiple classes in multiclass characters.
 *
 * D&D 5e multiclass rules allow a character to have the same spell from multiple sources:
 * - A Wizard/Cleric could have "Protection from Evil and Good" in their spellbook (Wizard)
 *   AND prepare it from the Cleric spell list (counts against Cleric prep limit).
 *
 * The old constraint (character_id + spell_slug) prevented this. The new constraint
 * (character_id + spell_slug + class_slug) allows the same spell with different class sources.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            // SQLite: Must recreate table to change unique constraint
            $this->recreateTableForSqlite();
        } else {
            // MySQL: First add the new unique index, then drop the old one
            // Adding first ensures character_id has an index for the FK constraint
            DB::statement('ALTER TABLE character_spells ADD UNIQUE INDEX character_spells_character_class_spell_unique (character_id, spell_slug, class_slug)');
            DB::statement('ALTER TABLE character_spells DROP INDEX character_spells_character_id_spell_slug_unique');
        }
    }

    /**
     * Recreate character_spells table with new unique constraint for SQLite.
     */
    private function recreateTableForSqlite(): void
    {
        // Create new table with correct unique constraint
        DB::statement('
            CREATE TABLE character_spells_new (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                character_id INTEGER NOT NULL,
                spell_slug VARCHAR(150) NOT NULL,
                preparation_status VARCHAR(20) DEFAULT "known",
                source VARCHAR(20) DEFAULT "class",
                max_uses INTEGER,
                uses_remaining INTEGER,
                resets_on VARCHAR(20),
                class_slug VARCHAR(150),
                level_acquired INTEGER,
                created_at TEXT,
                FOREIGN KEY (character_id) REFERENCES characters(id) ON DELETE CASCADE,
                UNIQUE (character_id, spell_slug, class_slug)
            )
        ');

        // Copy data
        DB::statement('
            INSERT INTO character_spells_new
            SELECT id, character_id, spell_slug, preparation_status, source, max_uses, uses_remaining, resets_on, class_slug, level_acquired, created_at
            FROM character_spells
        ');

        // Drop old table
        DB::statement('DROP TABLE character_spells');

        // Rename new table
        DB::statement('ALTER TABLE character_spells_new RENAME TO character_spells');

        // Recreate indexes
        DB::statement('CREATE INDEX character_spells_spell_slug_index ON character_spells (spell_slug)');
        DB::statement('CREATE INDEX character_spells_class_slug_index ON character_spells (class_slug)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            // For SQLite rollback, recreate with old constraint
            $this->recreateTableForSqliteRollback();
        } else {
            DB::statement('ALTER TABLE character_spells ADD UNIQUE INDEX character_spells_character_id_spell_slug_unique (character_id, spell_slug)');
            DB::statement('ALTER TABLE character_spells DROP INDEX character_spells_character_class_spell_unique');
        }
    }

    /**
     * Recreate character_spells table with old unique constraint for SQLite rollback.
     */
    private function recreateTableForSqliteRollback(): void
    {
        DB::statement('
            CREATE TABLE character_spells_new (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                character_id INTEGER NOT NULL,
                spell_slug VARCHAR(150) NOT NULL,
                preparation_status VARCHAR(20) DEFAULT "known",
                source VARCHAR(20) DEFAULT "class",
                max_uses INTEGER,
                uses_remaining INTEGER,
                resets_on VARCHAR(20),
                class_slug VARCHAR(150),
                level_acquired INTEGER,
                created_at TEXT,
                FOREIGN KEY (character_id) REFERENCES characters(id) ON DELETE CASCADE,
                UNIQUE (character_id, spell_slug)
            )
        ');

        DB::statement('
            INSERT INTO character_spells_new
            SELECT id, character_id, spell_slug, preparation_status, source, max_uses, uses_remaining, resets_on, class_slug, level_acquired, created_at
            FROM character_spells
        ');

        DB::statement('DROP TABLE character_spells');
        DB::statement('ALTER TABLE character_spells_new RENAME TO character_spells');
        DB::statement('CREATE INDEX character_spells_spell_slug_index ON character_spells (spell_slug)');
        DB::statement('CREATE INDEX character_spells_class_slug_index ON character_spells (class_slug)');
    }
};
