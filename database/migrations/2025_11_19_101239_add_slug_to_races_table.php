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
        Schema::table('races', function (Blueprint $table) {
            $table->string('slug')->unique()->after('id');
        });

        // Backfill slugs for existing races
        // Handle base races first, then subraces
        $races = DB::table('races')
            ->orderBy('parent_race_id', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        foreach ($races as $race) {
            $slug = $this->generateRaceSlug($race);

            DB::table('races')
                ->where('id', $race->id)
                ->update(['slug' => $slug]);
        }
    }

    /**
     * Generate slug for a race, handling parent/subrace relationships.
     */
    private function generateRaceSlug(object $race): string
    {
        // If this is a base race (no parent), just slug the name
        if ($race->parent_race_id === null) {
            return Str::slug($race->name);
        }

        // For subraces, extract the subrace portion
        // Format: "Dwarf (Hill)" or "Elf, High"
        $name = $race->name;

        // Try parentheses format first: "Dwarf (Hill)"
        if (preg_match('/^(.+?)\s*\((.+)\)$/', $name, $matches)) {
            $baseRaceName = trim($matches[1]);
            $subraceName = trim($matches[2]);
            return Str::slug($baseRaceName) . '-' . Str::slug($subraceName);
        }

        // Try comma format: "Dwarf, Hill"
        if (str_contains($name, ',')) {
            [$baseRaceName, $subraceName] = array_map('trim', explode(',', $name, 2));
            return Str::slug($baseRaceName) . '-' . Str::slug($subraceName);
        }

        // Fallback: just slug the full name
        return Str::slug($name);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('races', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }
};
