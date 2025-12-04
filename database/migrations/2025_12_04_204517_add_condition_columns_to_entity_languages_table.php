<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds condition support for language choices that depend on prior knowledge.
     *
     * Example: Clan Crafter - "Dwarvish, or one other of your choice if you already speak Dwarvish"
     * The choice row would have:
     * - condition_type = 'already_knows'
     * - condition_language_id = <dwarvish_id>
     *
     * API consumers can check if character already has the condition_language from race,
     * and only show the choice if the condition is met.
     */
    public function up(): void
    {
        Schema::table('entity_languages', function (Blueprint $table) {
            $table->string('condition_type')->nullable()->after('choice_option');
            $table->foreignId('condition_language_id')->nullable()->after('condition_type')
                ->constrained('languages')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('entity_languages', function (Blueprint $table) {
            $table->dropForeign(['condition_language_id']);
            $table->dropColumn(['condition_type', 'condition_language_id']);
        });
    }
};
