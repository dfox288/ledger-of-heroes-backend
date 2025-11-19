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
        Schema::table('feats', function (Blueprint $table) {
            $table->string('slug')->unique()->after('id');
        });

        // Backfill slugs for existing feats
        DB::table('feats')->orderBy('id')->each(function ($feat) {
            DB::table('feats')
                ->where('id', $feat->id)
                ->update(['slug' => Str::slug($feat->name)]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('feats', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }
};
