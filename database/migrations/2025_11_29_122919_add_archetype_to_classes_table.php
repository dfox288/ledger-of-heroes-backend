<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds archetype column to store the subclass category name for base classes.
     * Examples: "Martial Archetype" (Fighter), "Divine Domain" (Cleric), "Arcane Tradition" (Wizard)
     *
     * This is extracted from the XML during import from features like "Martial Archetype: Champion".
     * Only base classes have archetypes - subclasses inherit from their parent.
     */
    public function up(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->string('archetype', 100)->nullable()->after('description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->dropColumn('archetype');
        });
    }
};
