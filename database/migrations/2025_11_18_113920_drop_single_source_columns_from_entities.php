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
        $tables = ['spells', 'items', 'races', 'backgrounds', 'feats', 'classes', 'monsters'];

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $blueprint) use ($table) {
                    if (Schema::hasColumn($table, 'source_id')) {
                        $blueprint->dropForeign(["{$table}_source_id_foreign"]);
                        $blueprint->dropColumn('source_id');
                    }

                    if (Schema::hasColumn($table, 'source_pages')) {
                        $blueprint->dropColumn('source_pages');
                    }
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = ['spells', 'items', 'races', 'backgrounds', 'feats', 'classes', 'monsters'];

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->unsignedBigInteger('source_id')->nullable();
                    $blueprint->string('source_pages', 100)->nullable();

                    $blueprint->foreign('source_id')
                        ->references('id')
                        ->on('sources')
                        ->onDelete('cascade');
                });
            }
        }
    }
};
