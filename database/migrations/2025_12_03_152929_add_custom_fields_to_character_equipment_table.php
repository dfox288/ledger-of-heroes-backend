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
        Schema::table('character_equipment', function (Blueprint $table) {
            // Make item_id nullable to allow custom items
            $table->foreignId('item_id')->nullable()->change();

            // Add custom item fields after item_id
            $table->string('custom_name', 255)->nullable()->after('item_id');
            $table->text('custom_description')->nullable()->after('custom_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('character_equipment', function (Blueprint $table) {
            $table->dropColumn(['custom_name', 'custom_description']);
            $table->foreignId('item_id')->nullable(false)->change();
        });
    }
};
