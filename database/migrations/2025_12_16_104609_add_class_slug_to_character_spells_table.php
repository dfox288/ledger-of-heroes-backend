<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Issue #692: Add class_slug to track which class grants each spell.
     * This enables proper multiclass spellcasting display per PHB p.164-165.
     */
    public function up(): void
    {
        Schema::table('character_spells', function (Blueprint $table) {
            $table->string('class_slug', 150)->nullable()->after('source');
            $table->index('class_slug');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('character_spells', function (Blueprint $table) {
            $table->dropIndex(['class_slug']);
            $table->dropColumn('class_slug');
        });
    }
};
