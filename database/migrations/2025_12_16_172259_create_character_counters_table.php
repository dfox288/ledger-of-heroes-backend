<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates character_counters table for resource tracking.
 *
 * Counters track limited-use abilities separate from features:
 * - Features = abilities (Rage, Font of Magic)
 * - Counters = resource pools (Rage uses, Sorcery Points)
 *
 * Also drops unused max_uses/uses_remaining columns from
 * character_features and feature_selections tables.
 *
 * @see https://github.com/dfox288/ledger-of-heroes/issues/722
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('character_counters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('character_id')->constrained()->cascadeOnDelete();
            // Using string instead of enum for SQLite compatibility
            $table->string('source_type', 20); // 'class', 'feat', 'race'
            $table->string('source_slug', 150);
            $table->string('counter_name', 255);
            $table->smallInteger('current_uses')->nullable(); // null = full
            $table->smallInteger('max_uses'); // -1 = unlimited
            $table->char('reset_timing', 1)->nullable(); // S=short, L=long, D=daily
            $table->timestamps();

            $table->unique(
                ['character_id', 'source_type', 'source_slug', 'counter_name'],
                'char_counter_unique'
            );
            $table->index('source_slug');
        });

        // Drop unused columns from character_features
        Schema::table('character_features', function (Blueprint $table) {
            $table->dropColumn(['max_uses', 'uses_remaining']);
        });

        // Drop unused columns from feature_selections
        Schema::table('feature_selections', function (Blueprint $table) {
            $table->dropColumn(['max_uses', 'uses_remaining']);
        });
    }

    public function down(): void
    {
        // Restore columns to character_features
        Schema::table('character_features', function (Blueprint $table) {
            $table->tinyInteger('uses_remaining')->unsigned()->nullable();
            $table->tinyInteger('max_uses')->unsigned()->nullable();
        });

        // Restore columns to feature_selections
        Schema::table('feature_selections', function (Blueprint $table) {
            $table->tinyInteger('uses_remaining')->unsigned()->nullable();
            $table->tinyInteger('max_uses')->unsigned()->nullable();
        });

        Schema::dropIfExists('character_counters');
    }
};
