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
        Schema::table('monsters', function (Blueprint $table) {
            $table->string('slug')->unique()->after('id');
        });

        // Backfill slugs for existing monsters
        DB::table('monsters')->orderBy('id')->each(function ($monster) {
            DB::table('monsters')
                ->where('id', $monster->id)
                ->update(['slug' => Str::slug($monster->name)]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('monsters', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }
};
