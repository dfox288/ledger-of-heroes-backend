<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Races where the base race is complete (has 3+ ability score points)
     * and subrace selection is optional.
     */
    private array $optionalSubraceRaces = [
        'human',
        'dragonborn',
        'tiefling',
        'half-elf',
        'half-orc',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('races', function (Blueprint $table) {
            $table->boolean('subrace_required')->default(true)->after('parent_race_id');
        });

        // Update races where subrace selection is optional (base race is complete)
        DB::table('races')
            ->whereIn('slug', $this->optionalSubraceRaces)
            ->whereNull('parent_race_id')
            ->update(['subrace_required' => false]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('races', function (Blueprint $table) {
            $table->dropColumn('subrace_required');
        });
    }
};
