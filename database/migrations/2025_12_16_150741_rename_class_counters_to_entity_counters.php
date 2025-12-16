<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Converts class_counters to polymorphic entity_counters table.
 *
 * Previously: class_id OR feat_id as foreign keys
 * Now: reference_type/reference_id polymorphic pattern (matches entity_* tables)
 *
 * This allows counters for:
 * - CharacterClass (existing: 310+ records)
 * - Feat (existing: 52+ records)
 * - CharacterTrait (new: racial traits like Breath Weapon)
 */
return new class extends Migration
{
    public function up(): void
    {
        // Step 1: Add polymorphic columns to existing table
        Schema::table('class_counters', function (Blueprint $table) {
            $table->string('reference_type', 255)->nullable()->after('id');
            $table->unsignedBigInteger('reference_id')->nullable()->after('reference_type');
            $table->index(['reference_type', 'reference_id'], 'entity_counters_reference_index');
        });

        // Step 2: Migrate existing data
        // Convert class_id → App\Models\CharacterClass
        DB::table('class_counters')
            ->whereNotNull('class_id')
            ->update([
                'reference_type' => 'App\\Models\\CharacterClass',
                'reference_id' => DB::raw('class_id'),
            ]);

        // Convert feat_id → App\Models\Feat
        DB::table('class_counters')
            ->whereNotNull('feat_id')
            ->update([
                'reference_type' => 'App\\Models\\Feat',
                'reference_id' => DB::raw('feat_id'),
            ]);

        // Step 3: Make polymorphic columns required now that data is migrated
        Schema::table('class_counters', function (Blueprint $table) {
            $table->string('reference_type', 255)->nullable(false)->change();
            $table->unsignedBigInteger('reference_id')->nullable(false)->change();
        });

        // Step 4: Drop old foreign keys and columns
        Schema::table('class_counters', function (Blueprint $table) {
            $table->dropForeign(['class_id']);
            $table->dropForeign(['feat_id']);
        });

        Schema::table('class_counters', function (Blueprint $table) {
            $table->dropIndex(['feat_id']);
            $table->dropColumn(['class_id', 'feat_id']);
        });

        // Step 5: Rename table
        Schema::rename('class_counters', 'entity_counters');
    }

    public function down(): void
    {
        // Step 1: Rename table back
        Schema::rename('entity_counters', 'class_counters');

        // Step 2: Re-add old columns
        Schema::table('class_counters', function (Blueprint $table) {
            $table->unsignedBigInteger('class_id')->nullable()->after('id');
            $table->unsignedBigInteger('feat_id')->nullable()->after('class_id');
            $table->index('feat_id');
        });

        // Step 3: Migrate data back
        DB::table('class_counters')
            ->where('reference_type', 'App\\Models\\CharacterClass')
            ->update(['class_id' => DB::raw('reference_id')]);

        DB::table('class_counters')
            ->where('reference_type', 'App\\Models\\Feat')
            ->update(['feat_id' => DB::raw('reference_id')]);

        // Step 4: Re-add foreign keys
        Schema::table('class_counters', function (Blueprint $table) {
            $table->foreign('class_id')->references('id')->on('classes')->cascadeOnDelete();
            $table->foreign('feat_id')->references('id')->on('feats')->cascadeOnDelete();
        });

        // Step 5: Drop polymorphic columns
        Schema::table('class_counters', function (Blueprint $table) {
            $table->dropIndex('entity_counters_reference_index');
            $table->dropColumn(['reference_type', 'reference_id']);
        });
    }
};
