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
        Schema::table('skills', function (Blueprint $table) {
            $table->string('slug')->unique()->after('id');
        });

        // Backfill existing records with slugs
        DB::table('skills')->get()->each(function ($skill) {
            DB::table('skills')
                ->where('id', $skill->id)
                ->update(['slug' => Str::slug($skill->name)]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('skills', function (Blueprint $table) {
            $table->dropColumn('slug');
        });
    }
};
