<?php

use App\Models\ClassCounter;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extends class_counters table to support feat-based counters.
 *
 * Previously: class_id was required (class features only)
 * Now: Either class_id OR feat_id must be set (supports both)
 *
 * For feats, level is always 1 (no progression scaling).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('class_counters', function (Blueprint $table) {
            // Add feat_id as alternative to class_id
            $table->foreignId('feat_id')
                ->nullable()
                ->after('class_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->index('feat_id');
        });

        // Make class_id nullable (requires separate statement for MySQL)
        Schema::table('class_counters', function (Blueprint $table) {
            $table->unsignedBigInteger('class_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Delete feat counters before restoring class_id constraint
        // Without this, rollback would fail with integrity constraint violation
        ClassCounter::whereNotNull('feat_id')->delete();

        Schema::table('class_counters', function (Blueprint $table) {
            $table->dropForeign(['feat_id']);
            $table->dropIndex(['feat_id']);
            $table->dropColumn('feat_id');
        });

        // Restore class_id as required
        Schema::table('class_counters', function (Blueprint $table) {
            $table->unsignedBigInteger('class_id')->nullable(false)->change();
        });
    }
};
