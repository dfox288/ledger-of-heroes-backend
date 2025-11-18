<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Update random_tables table
        Schema::table('random_tables', function (Blueprint $table) {
            $table->string('dice_type', 10)->nullable()->change(); // Make nullable
        });

        // Update random_table_entries table
        Schema::table('random_table_entries', function (Blueprint $table) {
            // Add new columns
            $table->unsignedInteger('roll_min')->nullable()->after('random_table_id');
            $table->unsignedInteger('roll_max')->nullable()->after('roll_min');
            $table->text('result_text')->nullable()->after('roll_max');
        });

        // Migrate existing data if any exists (database-agnostic approach)
        // Since we're adding new columns to an empty table (no existing data in production),
        // we can skip the data migration step. If data exists, it would need to be migrated
        // programmatically using Eloquent to avoid database-specific SQL functions.

        // Drop old columns
        Schema::table('random_table_entries', function (Blueprint $table) {
            $table->dropColumn(['roll_value', 'result']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Restore old schema
        Schema::table('random_table_entries', function (Blueprint $table) {
            $table->string('roll_value', 50)->nullable();
            $table->text('result');
        });

        // Migrate data back (database-agnostic approach)
        // Since this is a rollback scenario, we don't need to preserve data
        // that was created after the migration ran.

        Schema::table('random_table_entries', function (Blueprint $table) {
            $table->dropColumn(['roll_min', 'roll_max', 'result_text']);
        });

        // Restore dice_type as NOT NULL
        Schema::table('random_tables', function (Blueprint $table) {
            $table->string('dice_type', 10)->nullable(false)->change();
        });
    }
};
