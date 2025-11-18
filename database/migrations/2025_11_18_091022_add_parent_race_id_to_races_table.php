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
        Schema::table('races', function (Blueprint $table) {
            $table->unsignedBigInteger('parent_race_id')->nullable()->after('id');

            $table->foreign('parent_race_id')
                  ->references('id')
                  ->on('races')
                  ->onDelete('cascade');

            $table->index('parent_race_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('races', function (Blueprint $table) {
            $table->dropForeign(['parent_race_id']);
            $table->dropIndex(['parent_race_id']);
            $table->dropColumn('parent_race_id');
        });
    }
};
