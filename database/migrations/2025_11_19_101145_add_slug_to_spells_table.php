<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('spells', function (Blueprint $table) {
            $table->string('slug')->unique()->after('id');
        });

        // Backfill slugs for existing spells
        DB::table('spells')->orderBy('id')->each(function ($spell) {
            DB::table('spells')
                ->where('id', $spell->id)
                ->update(['slug' => Str::slug($spell->name)]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('spells', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }
};
