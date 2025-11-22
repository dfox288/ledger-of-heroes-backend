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
        Schema::table('proficiency_types', function (Blueprint $table) {
            $table->string('slug')->unique()->after('id');
        });

        // Backfill existing records with slugs
        DB::table('proficiency_types')->get()->each(function ($type) {
            DB::table('proficiency_types')
                ->where('id', $type->id)
                ->update(['slug' => Str::slug($type->name)]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('proficiency_types', function (Blueprint $table) {
            $table->dropColumn('slug');
        });
    }
};
