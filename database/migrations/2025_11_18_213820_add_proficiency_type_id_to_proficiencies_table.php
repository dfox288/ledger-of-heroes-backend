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
        Schema::table('proficiencies', function (Blueprint $table) {
            $table->unsignedBigInteger('proficiency_type_id')->nullable()->after('proficiency_type');

            $table->foreign('proficiency_type_id')
                ->references('id')
                ->on('proficiency_types')
                ->onDelete('set null');

            $table->index('proficiency_type_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('proficiencies', function (Blueprint $table) {
            $table->dropForeign(['proficiency_type_id']);
            $table->dropIndex(['proficiency_type_id']);
            $table->dropColumn('proficiency_type_id');
        });
    }
};
