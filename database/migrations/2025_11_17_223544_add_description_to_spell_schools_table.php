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
        Schema::table('spell_schools', function (Blueprint $table) {
            $table->text('description')->nullable();
        });

        // Update descriptions for each spell school
        DB::table('spell_schools')->where('code', 'A')->update([
            'description' => 'Protective magic that wards against harm',
        ]);
        DB::table('spell_schools')->where('code', 'C')->update([
            'description' => 'Magic that summons creatures or creates objects',
        ]);
        DB::table('spell_schools')->where('code', 'D')->update([
            'description' => 'Magic that reveals information',
        ]);
        DB::table('spell_schools')->where('code', 'EN')->update([
            'description' => 'Magic that affects minds and behavior',
        ]);
        DB::table('spell_schools')->where('code', 'EV')->update([
            'description' => 'Magic that creates powerful elemental effects',
        ]);
        DB::table('spell_schools')->where('code', 'I')->update([
            'description' => 'Magic that deceives the senses',
        ]);
        DB::table('spell_schools')->where('code', 'N')->update([
            'description' => 'Magic that manipulates life force and death',
        ]);
        DB::table('spell_schools')->where('code', 'T')->update([
            'description' => 'Magic that transforms physical properties',
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('spell_schools', function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }
};
