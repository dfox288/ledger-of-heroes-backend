<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Removes legacy single-class columns now that multiclass support
     * uses the character_classes junction table instead.
     *
     * BREAKING CHANGE: This migration removes deprecated columns.
     * The down() method restores the column structure but NOT the data.
     *
     * Before running this migration in production:
     * 1. Ensure all clients have migrated to using the 'classes' array
     * 2. Verify character_classes junction table has all character data
     * 3. Take a database backup for safety
     *
     * Rollback considerations:
     * - The down() method recreates the columns with default values
     * - Data from the removed columns CANNOT be recovered automatically
     * - If rollback is needed, restore from backup or manually populate
     *   level/class_id from the character_classes junction table
     */
    public function up(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->dropForeign(['class_id']);
            $table->dropIndex(['class_id']);
            $table->dropColumn(['level', 'class_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->unsignedTinyInteger('level')->default(1)->after('name');
            $table->foreignId('class_id')->nullable()->after('race_id')->constrained('classes')->nullOnDelete();
            $table->index('class_id');
        });
    }
};
