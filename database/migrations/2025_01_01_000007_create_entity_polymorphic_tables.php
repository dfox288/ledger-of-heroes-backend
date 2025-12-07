<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates all entity_* polymorphic tables.
 * These tables use reference_type/reference_id for polymorphic relationships.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Entity Sources - links entities to their source books
        Schema::create('entity_sources', function (Blueprint $table) {
            $table->id();
            $table->string('reference_type', 255);
            $table->unsignedBigInteger('reference_id');
            $table->foreignId('source_id')->constrained()->cascadeOnDelete();
            $table->string('pages', 100)->nullable();

            $table->unique(['reference_type', 'reference_id', 'source_id'], 'entity_sources_unique');
            $table->index(['reference_type', 'reference_id']);
            $table->index('source_id');
        });

        // Entity Traits - polymorphic traits for backgrounds, classes, races
        Schema::create('entity_traits', function (Blueprint $table) {
            $table->id();
            $table->string('reference_type', 255);
            $table->unsignedBigInteger('reference_id');
            $table->string('name', 255);
            $table->string('category', 255)->nullable();
            $table->text('description');
            $table->integer('sort_order')->default(0);
            $table->unsignedBigInteger('entity_data_table_id')->nullable();
            // FK to entity_data_tables added after that table exists

            $table->index(['reference_type', 'reference_id']);
            $table->index('category');
            $table->index('entity_data_table_id', 'traits_random_table_id_index');
        });

        // Entity Data Tables (formerly random_tables) - structured data tables
        Schema::create('entity_data_tables', function (Blueprint $table) {
            $table->id();
            $table->string('reference_type', 255);
            $table->unsignedBigInteger('reference_id');
            $table->string('table_name', 255);
            $table->string('dice_type', 10)->nullable();
            $table->string('table_type', 20)->default('random');
            $table->text('description')->nullable();

            $table->index(['reference_type', 'reference_id']);
            $table->index('table_type');
        });

        // Add FK to entity_traits now that entity_data_tables exists
        Schema::table('entity_traits', function (Blueprint $table) {
            $table->foreign('entity_data_table_id', 'traits_random_table_id_foreign')
                ->references('id')->on('entity_data_tables')->nullOnDelete();
        });

        // Entity Data Table Entries
        Schema::create('entity_data_table_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_data_table_id')
                ->constrained('entity_data_tables')->cascadeOnDelete();
            $table->integer('roll_min')->unsigned()->nullable();
            $table->integer('roll_max')->unsigned()->nullable();
            $table->text('result_text')->nullable();
            $table->tinyInteger('level')->unsigned()->nullable()
                ->comment('Character level when this roll becomes available');
            $table->tinyInteger('resource_cost')->unsigned()->nullable()
                ->comment('Resource cost for this entry (ki points, sorcery points, etc.)');
            $table->integer('sort_order')->default(0);

            $table->index('entity_data_table_id', 'random_table_entries_random_table_id_index');
            $table->index('sort_order', 'random_table_entries_sort_order_index');
            $table->index('level', 'random_table_entries_level_index');
        });

        // Entity Conditions - conditions granted/imposed by entities
        Schema::create('entity_conditions', function (Blueprint $table) {
            $table->id();
            $table->string('reference_type', 255);
            $table->unsignedBigInteger('reference_id');
            $table->foreignId('condition_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('effect_type', 255);
            $table->text('description')->nullable();

            $table->index(['reference_type', 'reference_id']);
            $table->index('condition_id');
            $table->index('effect_type');
        });

        // Entity Languages - languages granted by entities
        Schema::create('entity_languages', function (Blueprint $table) {
            $table->id();
            $table->string('reference_type', 255);
            $table->unsignedBigInteger('reference_id');
            $table->foreignId('language_id')->nullable()->constrained()->cascadeOnDelete();
            $table->boolean('is_choice')->default(false)
                ->comment('true = player chooses, false = fixed language');
            $table->string('choice_group', 255)->nullable();
            $table->tinyInteger('choice_option')->unsigned()->nullable();
            $table->string('condition_type', 255)->nullable();
            $table->unsignedBigInteger('condition_language_id')->nullable();
            $table->tinyInteger('quantity')->unsigned()->default(1);

            $table->index(['reference_type', 'reference_id']);
            $table->index('language_id');
            $table->foreign('condition_language_id')->references('id')->on('languages')->nullOnDelete();
        });

        // Entity Senses - special senses for monsters/races
        Schema::create('entity_senses', function (Blueprint $table) {
            $table->id();
            $table->string('reference_type', 255);
            $table->unsignedBigInteger('reference_id');
            $table->foreignId('sense_id')->constrained()->cascadeOnDelete();
            $table->smallInteger('range_feet')->unsigned();
            $table->boolean('is_limited')->default(false);
            $table->string('notes', 100)->nullable();

            $table->unique(['reference_type', 'reference_id', 'sense_id'], 'entity_sense_unique');
            $table->index(['reference_type', 'reference_id']);
            $table->index('sense_id');
        });

        // Entity Saving Throws
        Schema::create('entity_saving_throws', function (Blueprint $table) {
            $table->id();
            $table->string('reference_type', 255);
            $table->unsignedBigInteger('reference_id');
            $table->foreignId('ability_score_id')->constrained('ability_scores')->cascadeOnDelete();
            $table->tinyInteger('dc')->unsigned()->nullable()
                ->comment('Difficulty Class for the saving throw (8-30, typically 10-20)');
            $table->string('save_effect', 50)->nullable()
                ->comment('negates, half_damage, ends_effect, reduced_duration');
            $table->boolean('is_initial_save')->default(true)
                ->comment('false = recurring save (e.g., end of each turn)');
            // Using string instead of enum for SQLite compatibility
            $table->string('save_modifier', 20)->default('none')
                ->comment('none = standard save; advantage = grants advantage; disadvantage = imposes disadvantage');
            $table->timestamps();

            $table->unique(
                ['reference_type', 'reference_id', 'ability_score_id', 'is_initial_save', 'save_modifier'],
                'unique_entity_ability_save_modifier'
            );
            $table->index(['reference_type', 'reference_id'], 'entity_saving_throws_entity_type_entity_id_index');
            $table->index('ability_score_id');
        });

        // Entity Prerequisites - prerequisites for feats, items, optional features
        Schema::create('entity_prerequisites', function (Blueprint $table) {
            $table->id();
            $table->string('reference_type', 255);
            $table->unsignedBigInteger('reference_id');
            $table->string('prerequisite_type', 255)->nullable();
            $table->unsignedBigInteger('prerequisite_id')->nullable();
            $table->tinyInteger('minimum_value')->unsigned()->nullable();
            $table->text('description')->nullable();
            $table->tinyInteger('group_id')->unsigned()->default(1);

            $table->index(['reference_type', 'reference_id']);
            $table->index(['prerequisite_type', 'prerequisite_id']);
            $table->index('group_id');
        });

        // Entity Modifiers - ability score bonuses, skill bonuses, etc.
        Schema::create('entity_modifiers', function (Blueprint $table) {
            $table->id();
            $table->string('reference_type', 255);
            $table->unsignedBigInteger('reference_id');
            $table->string('modifier_category', 255);
            $table->foreignId('ability_score_id')->nullable()
                ->constrained('ability_scores')->cascadeOnDelete();
            $table->foreignId('skill_id')->nullable()
                ->constrained('skills')->cascadeOnDelete();
            $table->foreignId('damage_type_id')->nullable()
                ->constrained('damage_types')->cascadeOnDelete();
            $table->string('value', 255);
            $table->boolean('is_choice')->default(false);
            $table->integer('choice_count')->nullable();
            $table->string('choice_constraint', 255)->nullable();
            $table->text('condition')->nullable();
            $table->tinyInteger('level')->unsigned()->nullable()
                ->comment('Character level when this modifier applies (e.g., ASI at level 4)');

            $table->index(['reference_type', 'reference_id'], 'modifiers_reference_type_reference_id_index');
            $table->index('modifier_category', 'modifiers_modifier_category_index');
            $table->index('level');
        });

        // Entity Proficiencies - proficiencies granted by entities
        Schema::create('entity_proficiencies', function (Blueprint $table) {
            $table->id();
            $table->string('reference_type', 255);
            $table->unsignedBigInteger('reference_id');
            $table->string('proficiency_type', 255);
            $table->string('proficiency_subcategory', 255)->nullable();
            $table->foreignId('proficiency_type_id')->nullable()
                ->constrained('proficiency_types')->nullOnDelete();
            $table->boolean('grants')->default(true)
                ->comment('true = entity grants proficiency, false = entity requires proficiency');
            $table->boolean('is_choice')->default(false);
            $table->string('choice_group', 255)->nullable()
                ->comment('Groups related proficiency options together');
            $table->integer('choice_option')->nullable()
                ->comment('Option number within a choice group');
            $table->integer('quantity')->nullable();
            $table->foreignId('skill_id')->nullable()
                ->constrained('skills')->cascadeOnDelete();
            $table->foreignId('item_id')->nullable()
                ->constrained('items')->cascadeOnDelete();
            $table->foreignId('ability_score_id')->nullable()
                ->constrained('ability_scores')->cascadeOnDelete();
            $table->string('proficiency_name', 255)->nullable();
            $table->tinyInteger('level')->unsigned()->nullable()
                ->comment('Character level when this proficiency is gained');

            $table->index(['reference_type', 'reference_id'], 'proficiencies_reference_type_reference_id_index');
            $table->index('proficiency_type', 'proficiencies_proficiency_type_index');
            $table->index('skill_id', 'proficiencies_skill_id_index');
            $table->index('item_id', 'proficiencies_item_id_index');
            $table->index('proficiency_type_id', 'proficiencies_proficiency_type_id_index');
            $table->index('is_choice', 'proficiencies_is_choice_index');
            $table->index('proficiency_subcategory', 'proficiencies_proficiency_subcategory_index');
            $table->index('choice_group', 'proficiencies_choice_group_index');
            $table->index('level', 'proficiencies_level_index');
        });

        // Entity Spells - spells granted by races, feats, items
        Schema::create('entity_spells', function (Blueprint $table) {
            $table->id();
            $table->string('reference_type', 255);
            $table->unsignedBigInteger('reference_id');
            $table->foreignId('spell_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('ability_score_id')->nullable()
                ->constrained('ability_scores')->nullOnDelete();
            $table->integer('level_requirement')->nullable();
            $table->string('usage_limit', 255)->nullable();
            $table->boolean('is_cantrip')->default(false);
            $table->boolean('is_choice')->default(false);
            $table->tinyInteger('choice_count')->unsigned()->nullable()
                ->comment('Number of spells player picks from this pool');
            $table->string('choice_group', 255)->nullable()
                ->comment('Groups rows representing same choice');
            $table->tinyInteger('max_level')->unsigned()->nullable()
                ->comment('0=cantrip, 1-9=max spell level for choice');
            $table->foreignId('school_id')->nullable()
                ->constrained('spell_schools')->nullOnDelete();
            $table->foreignId('class_id')->nullable()
                ->constrained('classes')->nullOnDelete();
            $table->boolean('is_ritual_only')->default(false);
            $table->smallInteger('charges_cost_min')->unsigned()->nullable()
                ->comment('Minimum charges to cast (0 = free, 1-50 = cost)');
            $table->smallInteger('charges_cost_max')->unsigned()->nullable()
                ->comment('Maximum charges to cast (same as min for fixed costs)');
            $table->string('charges_cost_formula', 100)->nullable()
                ->comment('Human-readable formula: "1 per spell level", "1-3 per use"');

            $table->index('spell_id');
            $table->index(['reference_type', 'reference_id']);
            $table->index(['reference_type', 'spell_id'], 'idx_entity_spells_type_spell');
            $table->index(['reference_type', 'spell_id'], 'entity_spells_type_spell_idx');
            $table->index(['reference_type', 'reference_id'], 'entity_spells_type_ref_idx');
            $table->index('is_choice');
            $table->index('choice_group');
        });

        // Entity Items - starting equipment for backgrounds/classes
        Schema::create('entity_items', function (Blueprint $table) {
            $table->id();
            $table->string('reference_type', 255);
            $table->unsignedBigInteger('reference_id');
            $table->foreignId('item_id')->nullable()->constrained()->cascadeOnDelete();
            $table->integer('quantity')->default(1);
            $table->boolean('is_choice')->default(false);
            $table->string('choice_group', 255)->nullable()
                ->comment('Groups related choice options together');
            $table->integer('choice_option')->nullable()
                ->comment('Option number within a choice group (1=a, 2=b, 3=c)');
            $table->text('choice_description')->nullable();
            $table->string('proficiency_subcategory', 255)->nullable();
            $table->text('description')->nullable();

            $table->index(['reference_type', 'reference_id']);
            $table->index('proficiency_subcategory');
            $table->index('choice_group');
        });

        // Equipment Choice Items - specific items within an equipment choice
        Schema::create('equipment_choice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_item_id')->constrained('entity_items')->cascadeOnDelete();
            $table->foreignId('proficiency_type_id')->nullable()
                ->constrained('proficiency_types')->nullOnDelete();
            $table->foreignId('item_id')->nullable()
                ->constrained('items')->nullOnDelete();
            $table->tinyInteger('quantity')->unsigned()->default(1);
            $table->tinyInteger('sort_order')->unsigned()->default(0);

            $table->index('entity_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_choice_items');
        Schema::dropIfExists('entity_items');
        Schema::dropIfExists('entity_spells');
        Schema::dropIfExists('entity_proficiencies');
        Schema::dropIfExists('entity_modifiers');
        Schema::dropIfExists('entity_prerequisites');
        Schema::dropIfExists('entity_saving_throws');
        Schema::dropIfExists('entity_senses');
        Schema::dropIfExists('entity_languages');
        Schema::dropIfExists('entity_conditions');
        Schema::dropIfExists('entity_data_table_entries');

        Schema::table('entity_traits', function (Blueprint $table) {
            $table->dropForeign('traits_random_table_id_foreign');
        });

        Schema::dropIfExists('entity_data_tables');
        Schema::dropIfExists('entity_traits');
        Schema::dropIfExists('entity_sources');
    }
};
