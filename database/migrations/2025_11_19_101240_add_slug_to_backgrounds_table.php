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
        Schema::table('backgrounds', function (Blueprint $table) {
            $table->string('slug')->unique()->after('id');
        });

        // Backfill slugs for existing backgrounds
        DB::table('backgrounds')->orderBy('id')->each(function ($background) {
            DB::table('backgrounds')
                ->where('id', $background->id)
                ->update(['slug' => Str::slug($background->name)]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('backgrounds', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }
};
