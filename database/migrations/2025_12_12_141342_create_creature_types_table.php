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
        Schema::create('creature_types', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 50)->unique();
            $table->string('name', 50);
            $table->boolean('typically_immune_to_poison')->default(false);
            $table->boolean('typically_immune_to_charmed')->default(false);
            $table->boolean('typically_immune_to_frightened')->default(false);
            $table->boolean('typically_immune_to_exhaustion')->default(false);
            $table->boolean('requires_sustenance')->default(true);
            $table->boolean('requires_sleep')->default(true);
            $table->text('description')->nullable();
        });

        // Add FK to monsters (nullable for migration safety)
        Schema::table('monsters', function (Blueprint $table) {
            $table->foreignId('creature_type_id')
                ->nullable()
                ->after('type')
                ->constrained('creature_types')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('monsters', function (Blueprint $table) {
            $table->dropForeign(['creature_type_id']);
            $table->dropColumn('creature_type_id');
        });

        Schema::dropIfExists('creature_types');
    }
};
