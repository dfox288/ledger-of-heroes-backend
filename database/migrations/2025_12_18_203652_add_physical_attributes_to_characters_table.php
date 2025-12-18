<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds physical description fields and deity for issue #758.
     */
    public function up(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->string('age', 50)->nullable()->after('alignment');
            $table->string('height', 50)->nullable()->after('age');
            $table->string('weight', 50)->nullable()->after('height');
            $table->string('eye_color', 50)->nullable()->after('weight');
            $table->string('hair_color', 50)->nullable()->after('eye_color');
            $table->string('skin_color', 50)->nullable()->after('hair_color');
            $table->string('deity', 150)->nullable()->after('skin_color');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->dropColumn([
                'age',
                'height',
                'weight',
                'eye_color',
                'hair_color',
                'skin_color',
                'deity',
            ]);
        });
    }
};
