<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Step 1: Rename main table
        Schema::rename('random_tables', 'entity_data_tables');

        // Step 2: Rename entries table
        Schema::rename('random_table_entries', 'entity_data_table_entries');

        // Step 3: Add table_type column with default
        Schema::table('entity_data_tables', function (Blueprint $table) {
            $table->string('table_type', 20)->default('random')->after('dice_type');
            $table->index('table_type');
        });

        // Step 4: Rename foreign key in entries table
        Schema::table('entity_data_table_entries', function (Blueprint $table) {
            $table->renameColumn('random_table_id', 'entity_data_table_id');
        });

        // Step 5: Rename foreign key in entity_traits table
        Schema::table('entity_traits', function (Blueprint $table) {
            $table->renameColumn('random_table_id', 'entity_data_table_id');
        });

        // Step 6: Populate table_type based on existing data patterns
        // Damage tables (contain "Damage" in name)
        DB::statement("UPDATE entity_data_tables SET table_type = 'damage' WHERE table_name LIKE '%Damage%'");

        // Modifier tables (contain "Modifier" in name)
        DB::statement("UPDATE entity_data_tables SET table_type = 'modifier' WHERE table_name LIKE '%Modifier%'");

        // Progression tables (Spells Known, Exhaustion, etc.)
        DB::statement("UPDATE entity_data_tables SET table_type = 'progression' WHERE table_name LIKE '%Spells Known%' OR table_name LIKE '%Exhaustion%' OR table_name LIKE '%Cantrips Known%'");

        // Lookup tables (no dice, still marked as random)
        DB::statement("UPDATE entity_data_tables SET table_type = 'lookup' WHERE (dice_type IS NULL OR dice_type = '') AND table_type = 'random'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Step 1: Rename foreign key in entity_traits back
        Schema::table('entity_traits', function (Blueprint $table) {
            $table->renameColumn('entity_data_table_id', 'random_table_id');
        });

        // Step 2: Rename foreign key in entries table back
        Schema::table('entity_data_table_entries', function (Blueprint $table) {
            $table->renameColumn('entity_data_table_id', 'random_table_id');
        });

        // Step 3: Drop table_type column
        Schema::table('entity_data_tables', function (Blueprint $table) {
            $table->dropIndex(['table_type']);
            $table->dropColumn('table_type');
        });

        // Step 4: Rename tables back
        Schema::rename('entity_data_table_entries', 'random_table_entries');
        Schema::rename('entity_data_tables', 'random_tables');
    }
};
