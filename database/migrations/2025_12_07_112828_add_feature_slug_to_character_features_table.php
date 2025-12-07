<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds feature_slug column for slug-based feature lookups and makes feature_id nullable.
     *
     * During the transition:
     * - Feat models use feature_slug (have full_slug)
     * - ClassFeature and CharacterTrait still use feature_id (don't have slugs yet)
     *
     * Part of Epic #288 - Slug-based character references.
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            // SQLite doesn't support ALTER COLUMN, so we recreate the table
            DB::statement('PRAGMA foreign_keys=off');

            // Create new table with nullable feature_id and feature_slug
            DB::statement('CREATE TABLE character_features_new (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                character_id INTEGER NOT NULL,
                feature_type VARCHAR(50) NOT NULL,
                feature_id INTEGER,
                feature_slug VARCHAR(150),
                source VARCHAR(20) DEFAULT \'class\' NOT NULL,
                level_acquired INTEGER DEFAULT 1 NOT NULL,
                uses_remaining INTEGER,
                max_uses INTEGER,
                created_at TIMESTAMP,
                FOREIGN KEY (character_id) REFERENCES characters(id) ON DELETE CASCADE
            )');

            // Copy data
            DB::statement('INSERT INTO character_features_new
                SELECT id, character_id, feature_type, feature_id, NULL, source, level_acquired, uses_remaining, max_uses, created_at
                FROM character_features');

            // Drop old table and rename new
            DB::statement('DROP TABLE character_features');
            DB::statement('ALTER TABLE character_features_new RENAME TO character_features');

            // Recreate indexes
            DB::statement('CREATE INDEX character_features_character_id_index ON character_features(character_id)');
            DB::statement('CREATE INDEX character_features_feature_type_feature_id_index ON character_features(feature_type, feature_id)');
            DB::statement('CREATE INDEX character_features_feature_slug_index ON character_features(feature_slug)');

            DB::statement('PRAGMA foreign_keys=on');
        } else {
            // MySQL: Use ALTER COLUMN to make feature_id nullable
            DB::statement('ALTER TABLE character_features MODIFY feature_id BIGINT UNSIGNED NULL');

            // Add feature_slug column
            Schema::table('character_features', function (Blueprint $table) {
                $table->string('feature_slug', 150)->nullable()->after('feature_id');
                $table->index('feature_slug');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys=off');

            // Create table without feature_slug and with required feature_id
            DB::statement('CREATE TABLE character_features_new (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                character_id INTEGER NOT NULL,
                feature_type VARCHAR(50) NOT NULL,
                feature_id INTEGER NOT NULL,
                source VARCHAR(20) DEFAULT \'class\' NOT NULL,
                level_acquired INTEGER DEFAULT 1 NOT NULL,
                uses_remaining INTEGER,
                max_uses INTEGER,
                created_at TIMESTAMP,
                FOREIGN KEY (character_id) REFERENCES characters(id) ON DELETE CASCADE
            )');

            // Copy data (will fail if there are null feature_ids)
            DB::statement('INSERT INTO character_features_new
                SELECT id, character_id, feature_type, feature_id, source, level_acquired, uses_remaining, max_uses, created_at
                FROM character_features WHERE feature_id IS NOT NULL');

            DB::statement('DROP TABLE character_features');
            DB::statement('ALTER TABLE character_features_new RENAME TO character_features');

            DB::statement('CREATE INDEX character_features_character_id_index ON character_features(character_id)');
            DB::statement('CREATE INDEX character_features_feature_type_feature_id_index ON character_features(feature_type, feature_id)');

            DB::statement('PRAGMA foreign_keys=on');
        } else {
            Schema::table('character_features', function (Blueprint $table) {
                $table->dropIndex(['feature_slug']);
                $table->dropColumn('feature_slug');
            });

            DB::statement('ALTER TABLE character_features MODIFY feature_id BIGINT UNSIGNED NOT NULL');
        }
    }
};
