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
        Schema::table('entity_saving_throws', function (Blueprint $table) {
            // Rename entity_type to reference_type for consistency with other polymorphic tables
            $table->renameColumn('entity_type', 'reference_type');
            $table->renameColumn('entity_id', 'reference_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('entity_saving_throws', function (Blueprint $table) {
            // Revert to original column names
            $table->renameColumn('reference_type', 'entity_type');
            $table->renameColumn('reference_id', 'entity_id');
        });
    }
};
