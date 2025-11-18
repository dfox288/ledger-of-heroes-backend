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
        Schema::table('traits', function (Blueprint $table) {
            $table->unsignedBigInteger('random_table_id')->nullable()->after('sort_order');

            $table->foreign('random_table_id')
                ->references('id')
                ->on('random_tables')
                ->onDelete('set null');

            $table->index('random_table_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('traits', function (Blueprint $table) {
            $table->dropForeign(['random_table_id']);
            $table->dropIndex(['random_table_id']);
            $table->dropColumn('random_table_id');
        });
    }
};
