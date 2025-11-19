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
        Schema::table('entity_conditions', function (Blueprint $table) {
            // Make condition_id nullable to support free-form descriptions
            $table->unsignedBigInteger('condition_id')->nullable()->change();

            // Add description field for feat-style conditions
            $table->text('description')->nullable()->after('effect_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('entity_conditions', function (Blueprint $table) {
            $table->dropColumn('description');
            $table->unsignedBigInteger('condition_id')->nullable(false)->change();
        });
    }
};
