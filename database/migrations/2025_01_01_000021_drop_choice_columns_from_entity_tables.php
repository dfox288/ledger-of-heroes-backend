<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop equipment_choice_items table entirely (must be before entity_items changes)
        Schema::dropIfExists('equipment_choice_items');

        // entity_languages: drop choice columns
        Schema::table('entity_languages', function (Blueprint $table) {
            // Drop foreign key first
            $table->dropForeign(['condition_language_id']);
            $table->dropColumn([
                'is_choice',
                'choice_group',
                'choice_option',
                'condition_type',
                'condition_language_id',
                'quantity',
            ]);
        });

        // entity_spells: drop choice columns
        Schema::table('entity_spells', function (Blueprint $table) {
            // Drop foreign keys first
            $table->dropForeign(['school_id']);
            $table->dropForeign(['class_id']);
            // Drop indexes
            $table->dropIndex(['is_choice']);
            $table->dropIndex(['choice_group']);
            $table->dropColumn([
                'is_choice',
                'choice_count',
                'choice_group',
                'max_level',
                'school_id',
                'class_id',
                'is_ritual_only',
            ]);
            // Keep: is_cantrip (needed for fixed cantrip grants)
        });

        // entity_proficiencies: drop choice columns
        Schema::table('entity_proficiencies', function (Blueprint $table) {
            // Drop indexes first
            $table->dropIndex('proficiencies_is_choice_index');
            $table->dropIndex('proficiencies_choice_group_index');
            $table->dropColumn([
                'is_choice',
                'choice_group',
                'choice_option',
                'quantity',
            ]);
        });

        // entity_modifiers: drop choice columns
        Schema::table('entity_modifiers', function (Blueprint $table) {
            $table->dropColumn([
                'is_choice',
                'choice_count',
                'choice_constraint',
            ]);
        });

        // entity_items: drop choice columns
        Schema::table('entity_items', function (Blueprint $table) {
            // Drop indexes first
            $table->dropIndex(['proficiency_subcategory']);
            $table->dropIndex(['choice_group']);
            $table->dropColumn([
                'is_choice',
                'choice_group',
                'choice_option',
                'choice_description',
                'proficiency_subcategory',
            ]);
        });
    }

    public function down(): void
    {
        // This migration is not reversible - requires re-import
        throw new \RuntimeException('This migration cannot be reversed. Run migrate:fresh and re-import.');
    }
};
