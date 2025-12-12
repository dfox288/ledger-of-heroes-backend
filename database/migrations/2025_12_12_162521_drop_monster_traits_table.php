<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Monster traits have been migrated to the polymorphic entity_traits table.
     * This migration removes the now-redundant monster_traits table.
     */
    public function up(): void
    {
        Schema::dropIfExists('monster_traits');
    }

    /**
     * Reverse the migrations.
     *
     * Recreates the legacy monster_traits table structure.
     * Note: Data will not be restored - a re-import is required.
     */
    public function down(): void
    {
        Schema::create('monster_traits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monster_id')->constrained()->cascadeOnDelete();
            $table->string('name', 255);
            $table->text('description');
            $table->text('attack_data')->nullable();
            $table->smallInteger('sort_order')->unsigned()->default(0);
        });
    }
};
