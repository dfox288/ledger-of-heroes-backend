<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entity_choices', function (Blueprint $table) {
            $table->id();

            // Polymorphic reference (matches existing entity_* pattern)
            $table->string('reference_type');
            $table->unsignedBigInteger('reference_id');

            // Core choice fields
            $table->string('choice_type', 50);                    // language, spell, proficiency, ability_score, equipment
            $table->string('choice_group');                       // groups related options
            $table->unsignedTinyInteger('quantity')->default(1);  // how many to pick
            $table->string('constraint', 50)->nullable();         // 'different', etc.
            $table->unsignedTinyInteger('choice_option')->nullable(); // 1=a, 2=b, 3=c (for restricted choices)

            // Target (slug-based for stability across re-imports)
            $table->string('target_type', 50)->nullable();        // 'spell', 'language', 'skill', 'item', 'proficiency_type', 'ability_score'
            $table->string('target_slug')->nullable();            // 'phb:fireball', 'common', 'acrobatics'

            // Spell constraints (promoted for query performance)
            $table->unsignedTinyInteger('spell_max_level')->nullable(); // 0=cantrip, 1-9
            $table->string('spell_list_slug')->nullable();              // phb:wizard, phb:cleric, etc.
            $table->string('spell_school_slug')->nullable();            // evocation, necromancy, etc.

            // Proficiency constraints (promoted)
            $table->string('proficiency_type', 50)->nullable();   // skill, tool, weapon, armor, saving_throw

            // Rare/edge-case constraints (JSON)
            $table->json('constraints')->nullable();

            // Metadata
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('level_granted')->default(1);
            $table->boolean('is_required')->default(true);

            $table->timestamps();

            // Indexes
            $table->index(['reference_type', 'reference_id'], 'entity_choices_reference_idx');
            $table->index(['reference_type', 'reference_id', 'choice_type'], 'entity_choices_ref_type_idx');
            $table->index(['choice_type', 'spell_max_level'], 'entity_choices_spell_level_idx');
            $table->index(['choice_type', 'proficiency_type'], 'entity_choices_prof_type_idx');
            $table->index('choice_group', 'entity_choices_group_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entity_choices');
    }
};
