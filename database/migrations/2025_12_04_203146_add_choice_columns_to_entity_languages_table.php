<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds choice_group and choice_option columns to support restricted language choices.
     * Pattern matches entity_proficiencies for consistency.
     *
     * Example: Feylost background - "One of your choice of Elvish, Gnomish, Goblin, or Sylvan"
     * - Row 1: language_id=Elvish, is_choice=true, choice_group='lang_choice_1', choice_option=1, quantity=1
     * - Row 2: language_id=Gnomish, is_choice=true, choice_group='lang_choice_1', choice_option=2, quantity=null
     * - Row 3: language_id=Goblin, is_choice=true, choice_group='lang_choice_1', choice_option=3, quantity=null
     * - Row 4: language_id=Sylvan, is_choice=true, choice_group='lang_choice_1', choice_option=4, quantity=null
     */
    public function up(): void
    {
        Schema::table('entity_languages', function (Blueprint $table) {
            $table->string('choice_group')->nullable()->after('is_choice');
            $table->unsignedTinyInteger('choice_option')->nullable()->after('choice_group');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('entity_languages', function (Blueprint $table) {
            $table->dropColumn(['choice_group', 'choice_option']);
        });
    }
};
