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
        Schema::table('spell_effects', function (Blueprint $table) {
            $table->unsignedBigInteger('damage_type_id')->nullable()->after('scaling_increment');

            $table->foreign('damage_type_id')
                ->references('id')
                ->on('damage_types')
                ->onDelete('restrict');

            $table->index('damage_type_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('spell_effects', function (Blueprint $table) {
            $table->dropForeign(['damage_type_id']);
            $table->dropIndex(['damage_type_id']);
            $table->dropColumn('damage_type_id');
        });
    }
};
