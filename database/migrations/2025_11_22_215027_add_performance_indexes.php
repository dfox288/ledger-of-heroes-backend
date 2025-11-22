<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add indexes to entity_spells for monster spell queries
        Schema::table('entity_spells', function (Blueprint $table) {
            $table->index(['reference_type', 'spell_id'], 'entity_spells_type_spell_idx');
            $table->index(['reference_type', 'reference_id'], 'entity_spells_type_ref_idx');
        });

        // Add indexes to monsters for common filter queries
        Schema::table('monsters', function (Blueprint $table) {
            $table->index('slug', 'monsters_slug_idx');
            $table->index('challenge_rating', 'monsters_cr_idx');
            $table->index('type', 'monsters_type_idx');
            $table->index('size_id', 'monsters_size_idx');
        });

        // Add indexes to spells for common queries
        Schema::table('spells', function (Blueprint $table) {
            $table->index('slug', 'spells_slug_idx');
            $table->index('level', 'spells_level_idx');
        });

        // Add indexes to items for slug lookups
        Schema::table('items', function (Blueprint $table) {
            $table->index('slug', 'items_slug_idx');
        });

        // Add indexes to races for slug lookups
        Schema::table('races', function (Blueprint $table) {
            $table->index('slug', 'races_slug_idx');
        });

        // Add indexes to classes for slug lookups
        Schema::table('classes', function (Blueprint $table) {
            $table->index('slug', 'classes_slug_idx');
        });

        // Add indexes to backgrounds for slug lookups
        Schema::table('backgrounds', function (Blueprint $table) {
            $table->index('slug', 'backgrounds_slug_idx');
        });

        // Add indexes to feats for slug lookups
        Schema::table('feats', function (Blueprint $table) {
            $table->index('slug', 'feats_slug_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('entity_spells', function (Blueprint $table) {
            $table->dropIndex('entity_spells_type_spell_idx');
            $table->dropIndex('entity_spells_type_ref_idx');
        });

        Schema::table('monsters', function (Blueprint $table) {
            $table->dropIndex('monsters_slug_idx');
            $table->dropIndex('monsters_cr_idx');
            $table->dropIndex('monsters_type_idx');
            $table->dropIndex('monsters_size_idx');
        });

        Schema::table('spells', function (Blueprint $table) {
            $table->dropIndex('spells_slug_idx');
            $table->dropIndex('spells_level_idx');
        });

        Schema::table('items', function (Blueprint $table) {
            $table->dropIndex('items_slug_idx');
        });

        Schema::table('races', function (Blueprint $table) {
            $table->dropIndex('races_slug_idx');
        });

        Schema::table('classes', function (Blueprint $table) {
            $table->dropIndex('classes_slug_idx');
        });

        Schema::table('backgrounds', function (Blueprint $table) {
            $table->dropIndex('backgrounds_slug_idx');
        });

        Schema::table('feats', function (Blueprint $table) {
            $table->dropIndex('feats_slug_idx');
        });
    }
};
