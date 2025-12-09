<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds support for races with size choice (e.g., Custom Lineage can be Small or Medium).
     */
    public function up(): void
    {
        Schema::table('races', function (Blueprint $table) {
            $table->boolean('has_size_choice')->default(false)->after('size_id');
        });

        Schema::table('characters', function (Blueprint $table) {
            // Nullable size override - when set, overrides the race's default size
            $table->foreignId('size_id')->nullable()->after('race_slug')->constrained('sizes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->dropForeign(['size_id']);
            $table->dropColumn('size_id');
        });

        Schema::table('races', function (Blueprint $table) {
            $table->dropColumn('has_size_choice');
        });
    }
};
