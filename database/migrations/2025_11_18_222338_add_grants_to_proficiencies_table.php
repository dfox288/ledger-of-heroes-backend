<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('proficiencies', function (Blueprint $table) {
            $table->boolean('grants')->default(true)
                ->after('proficiency_type_id')
                ->comment('true = entity grants proficiency (Race/Background/Class), false = entity requires proficiency (Item/Spell)');
        });

        // Data migration: Set grants=false for Items and Spells
        DB::table('proficiencies')
            ->where('reference_type', 'App\\Models\\Item')
            ->update(['grants' => false]);

        DB::table('proficiencies')
            ->where('reference_type', 'App\\Models\\Spell')
            ->update(['grants' => false]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('proficiencies', function (Blueprint $table) {
            $table->dropColumn('grants');
        });
    }
};
