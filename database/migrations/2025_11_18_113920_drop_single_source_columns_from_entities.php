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
            if (!Schema::hasTable($table)) {
                continue;
            }

            // Get foreign keys from information_schema
            $foreignKeys = DB::select("
                SELECT CONSTRAINT_NAME
                FROM information_schema.TABLE_CONSTRAINTS
                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = '$table'
                AND CONSTRAINT_TYPE = 'FOREIGN KEY'
                AND CONSTRAINT_NAME LIKE '%source_id%'
            ");

            // Drop foreign keys using raw SQL
            foreach ($foreignKeys as $fk) {
                DB::statement("ALTER TABLE `$table` DROP FOREIGN KEY `{$fk->CONSTRAINT_NAME}`");
            }

            // Now drop the columns
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                if (Schema::hasColumn($table, 'source_id')) {
                    $blueprint->dropColumn('source_id');
                }

                if (Schema::hasColumn($table, 'source_pages')) {
                    $blueprint->dropColumn('source_pages');
                }
            });
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
