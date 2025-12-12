<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remove unused entity_data_table_id FK from entity_traits.
 *
 * This FK was set during import but never read back. The canonical relationship
 * is the MorphMany on EntityDataTable (dataTables points back to trait via reference_type/id).
 *
 * Related: Issue #505
 */
return new class extends Migration
{
    public function up(): void
    {
        // Check if the column exists before trying to drop it
        if (! Schema::hasColumn('entity_traits', 'entity_data_table_id')) {
            return;
        }

        Schema::table('entity_traits', function (Blueprint $table) {
            // MySQL uses custom FK name from base migration
            if (config('database.default') === 'mysql') {
                $table->dropForeign('traits_random_table_id_foreign');
            } else {
                // SQLite uses column array
                $table->dropForeign(['entity_data_table_id']);
            }
            $table->dropIndex('traits_random_table_id_index');
            $table->dropColumn('entity_data_table_id');
        });
    }

    public function down(): void
    {
        Schema::table('entity_traits', function (Blueprint $table) {
            $table->unsignedBigInteger('entity_data_table_id')->nullable()->after('sort_order');
            $table->index('entity_data_table_id', 'traits_random_table_id_index');
            $table->foreign('entity_data_table_id', 'traits_random_table_id_foreign')
                ->references('id')->on('entity_data_tables')->nullOnDelete();
        });
    }
};
