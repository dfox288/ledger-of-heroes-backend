<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Converts character tables from ID-based to slug-based references.
     * This is a breaking change - existing character data will not be migrated.
     */
    public function up(): void
    {
        // Characters table: race_id, background_id -> race_slug, background_slug
        Schema::table('characters', function (Blueprint $table) {
            $table->dropForeign(['race_id']);
            $table->dropForeign(['background_id']);
            $table->dropIndex(['race_id']);
        });

        Schema::table('characters', function (Blueprint $table) {
            $table->dropColumn(['race_id', 'background_id']);
        });

        Schema::table('characters', function (Blueprint $table) {
            $table->string('race_slug', 150)->nullable()->after('name');
            $table->string('background_slug', 150)->nullable()->after('race_slug');
            $table->index('race_slug');
            $table->index('background_slug');
        });

        // Character_classes table: class_id, subclass_id -> class_slug, subclass_slug
        Schema::table('character_classes', function (Blueprint $table) {
            $table->dropForeign(['class_id']);
            $table->dropForeign(['subclass_id']);
            $table->dropUnique(['character_id', 'class_id']);
            $table->dropIndex(['class_id']);
        });

        Schema::table('character_classes', function (Blueprint $table) {
            $table->dropColumn(['class_id', 'subclass_id']);
        });

        Schema::table('character_classes', function (Blueprint $table) {
            $table->string('class_slug', 150)->after('character_id');
            $table->string('subclass_slug', 150)->nullable()->after('class_slug');
            $table->index('class_slug');
            $table->unique(['character_id', 'class_slug']);
        });

        // Character_spells table: spell_id -> spell_slug
        // Has: foreign key, unique [character_id, spell_id], index on spell_id
        Schema::table('character_spells', function (Blueprint $table) {
            $table->dropForeign(['spell_id']);
            $table->dropUnique(['character_id', 'spell_id']);
            $table->dropIndex(['spell_id']);
        });

        Schema::table('character_spells', function (Blueprint $table) {
            $table->dropColumn('spell_id');
        });

        Schema::table('character_spells', function (Blueprint $table) {
            $table->string('spell_slug', 150)->after('character_id');
            $table->index('spell_slug');
            $table->unique(['character_id', 'spell_slug']);
        });

        // Character_equipment table: item_id -> item_slug
        // Has: foreign key, index on item_id
        Schema::table('character_equipment', function (Blueprint $table) {
            $table->dropForeign(['item_id']);
            $table->dropIndex(['item_id']);
        });

        Schema::table('character_equipment', function (Blueprint $table) {
            $table->dropColumn('item_id');
        });

        Schema::table('character_equipment', function (Blueprint $table) {
            $table->string('item_slug', 150)->nullable()->after('character_id');
            $table->index('item_slug');
        });

        // Character_languages table: language_id -> language_slug
        // Has: foreign key, unique [character_id, language_id]
        Schema::table('character_languages', function (Blueprint $table) {
            $table->dropForeign(['language_id']);
            $table->dropUnique(['character_id', 'language_id']);
        });

        Schema::table('character_languages', function (Blueprint $table) {
            $table->dropColumn('language_id');
        });

        Schema::table('character_languages', function (Blueprint $table) {
            $table->string('language_slug', 150)->after('character_id');
            $table->index('language_slug');
            $table->unique(['character_id', 'language_slug']);
        });

        // Character_proficiencies table: skill_id, proficiency_type_id -> skill_slug, proficiency_type_slug
        // Has: foreign keys, indexes on both
        Schema::table('character_proficiencies', function (Blueprint $table) {
            $table->dropForeign(['proficiency_type_id']);
            $table->dropForeign(['skill_id']);
            $table->dropIndex(['proficiency_type_id']);
            $table->dropIndex(['skill_id']);
        });

        Schema::table('character_proficiencies', function (Blueprint $table) {
            $table->dropColumn(['proficiency_type_id', 'skill_id']);
        });

        Schema::table('character_proficiencies', function (Blueprint $table) {
            $table->string('proficiency_type_slug', 150)->nullable()->after('character_id');
            $table->string('skill_slug', 150)->nullable()->after('proficiency_type_slug');
            $table->index('skill_slug');
            $table->index('proficiency_type_slug');
        });

        // Character_conditions table: condition_id -> condition_slug
        // Has: foreign key, unique [character_id, condition_id]
        Schema::table('character_conditions', function (Blueprint $table) {
            $table->dropForeign(['condition_id']);
            $table->dropUnique(['character_id', 'condition_id']);
        });

        Schema::table('character_conditions', function (Blueprint $table) {
            $table->dropColumn('condition_id');
        });

        Schema::table('character_conditions', function (Blueprint $table) {
            $table->string('condition_slug', 150)->after('character_id');
            $table->index('condition_slug');
            $table->unique(['character_id', 'condition_slug']);
        });

        // Feature_selections table: optional_feature_id, class_id -> optional_feature_slug, class_slug
        // Has: foreign keys, unique [character_id, optional_feature_id], index [character_id, class_id]
        // Note: Table was renamed from character_optional_features, so index names use old table name
        Schema::table('feature_selections', function (Blueprint $table) {
            $table->dropForeign(['optional_feature_id']);
            $table->dropForeign(['class_id']);
            $table->dropUnique('char_opt_feature_unique');
            // Index was created with old table name
            $table->dropIndex('character_optional_features_character_id_class_id_index');
        });

        Schema::table('feature_selections', function (Blueprint $table) {
            $table->dropColumn(['optional_feature_id', 'class_id']);
        });

        Schema::table('feature_selections', function (Blueprint $table) {
            $table->string('optional_feature_slug', 150)->after('character_id');
            $table->string('class_slug', 150)->nullable()->after('optional_feature_slug');
            $table->index('optional_feature_slug');
            $table->index('class_slug');
            $table->unique(['character_id', 'optional_feature_slug'], 'char_opt_feature_slug_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Feature_selections: restore IDs
        Schema::table('feature_selections', function (Blueprint $table) {
            $table->dropUnique('char_opt_feature_slug_unique');
            $table->dropIndex(['optional_feature_slug']);
            $table->dropIndex(['class_slug']);
        });

        Schema::table('feature_selections', function (Blueprint $table) {
            $table->dropColumn(['optional_feature_slug', 'class_slug']);
        });

        Schema::table('feature_selections', function (Blueprint $table) {
            $table->foreignId('optional_feature_id')->constrained()->cascadeOnDelete();
            $table->foreignId('class_id')->nullable()->constrained('classes')->nullOnDelete();
            $table->unique(['character_id', 'optional_feature_id'], 'char_opt_feature_unique');
            $table->index(['character_id', 'class_id']);
        });

        // Character_conditions: restore condition_id
        Schema::table('character_conditions', function (Blueprint $table) {
            $table->dropUnique(['character_id', 'condition_slug']);
            $table->dropIndex(['condition_slug']);
        });

        Schema::table('character_conditions', function (Blueprint $table) {
            $table->dropColumn('condition_slug');
        });

        Schema::table('character_conditions', function (Blueprint $table) {
            $table->foreignId('condition_id')->constrained()->cascadeOnDelete();
            $table->unique(['character_id', 'condition_id']);
        });

        // Character_proficiencies: restore IDs
        Schema::table('character_proficiencies', function (Blueprint $table) {
            $table->dropIndex(['skill_slug']);
            $table->dropIndex(['proficiency_type_slug']);
        });

        Schema::table('character_proficiencies', function (Blueprint $table) {
            $table->dropColumn(['skill_slug', 'proficiency_type_slug']);
        });

        Schema::table('character_proficiencies', function (Blueprint $table) {
            $table->foreignId('proficiency_type_id')->nullable()->constrained();
            $table->foreignId('skill_id')->nullable()->constrained();
            $table->index('proficiency_type_id');
            $table->index('skill_id');
        });

        // Character_languages: restore language_id
        Schema::table('character_languages', function (Blueprint $table) {
            $table->dropUnique(['character_id', 'language_slug']);
            $table->dropIndex(['language_slug']);
        });

        Schema::table('character_languages', function (Blueprint $table) {
            $table->dropColumn('language_slug');
        });

        Schema::table('character_languages', function (Blueprint $table) {
            $table->foreignId('language_id')->constrained();
            $table->unique(['character_id', 'language_id']);
        });

        // Character_equipment: restore item_id
        Schema::table('character_equipment', function (Blueprint $table) {
            $table->dropIndex(['item_slug']);
        });

        Schema::table('character_equipment', function (Blueprint $table) {
            $table->dropColumn('item_slug');
        });

        Schema::table('character_equipment', function (Blueprint $table) {
            $table->foreignId('item_id')->nullable()->constrained();
            $table->index('item_id');
        });

        // Character_spells: restore spell_id
        Schema::table('character_spells', function (Blueprint $table) {
            $table->dropUnique(['character_id', 'spell_slug']);
            $table->dropIndex(['spell_slug']);
        });

        Schema::table('character_spells', function (Blueprint $table) {
            $table->dropColumn('spell_slug');
        });

        Schema::table('character_spells', function (Blueprint $table) {
            $table->foreignId('spell_id')->constrained();
            $table->unique(['character_id', 'spell_id']);
            $table->index('spell_id');
        });

        // Character_classes: restore IDs
        Schema::table('character_classes', function (Blueprint $table) {
            $table->dropUnique(['character_id', 'class_slug']);
            $table->dropIndex(['class_slug']);
        });

        Schema::table('character_classes', function (Blueprint $table) {
            $table->dropColumn(['class_slug', 'subclass_slug']);
        });

        Schema::table('character_classes', function (Blueprint $table) {
            $table->unsignedBigInteger('class_id')->after('character_id');
            $table->unsignedBigInteger('subclass_id')->nullable()->after('class_id');
            $table->foreign('class_id')->references('id')->on('classes');
            $table->foreign('subclass_id')->references('id')->on('classes');
            $table->unique(['character_id', 'class_id']);
            $table->index('class_id');
        });

        // Characters: restore IDs
        Schema::table('characters', function (Blueprint $table) {
            $table->dropIndex(['race_slug']);
            $table->dropIndex(['background_slug']);
        });

        Schema::table('characters', function (Blueprint $table) {
            $table->dropColumn(['race_slug', 'background_slug']);
        });

        Schema::table('characters', function (Blueprint $table) {
            $table->foreignId('race_id')->nullable()->constrained('races')->nullOnDelete();
            $table->foreignId('background_id')->nullable()->constrained('backgrounds')->nullOnDelete();
            $table->index('race_id');
        });
    }
};
