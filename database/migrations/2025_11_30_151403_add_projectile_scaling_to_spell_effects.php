<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds projectile scaling fields for spells like Magic Missile and Scorching Ray
     * that create additional projectiles at higher spell slot levels.
     *
     * Example: Magic Missile
     *   - projectile_count: 3 (base darts at 1st level)
     *   - projectile_per_level: 1 (additional dart per slot level above 1st)
     *   - projectile_name: "dart" (for display purposes)
     */
    public function up(): void
    {
        Schema::table('spell_effects', function (Blueprint $table) {
            $table->unsignedTinyInteger('projectile_count')->nullable()->after('scaling_increment')
                ->comment('Base number of projectiles/targets at minimum spell slot');
            $table->unsignedTinyInteger('projectile_per_level')->nullable()->after('projectile_count')
                ->comment('Additional projectiles per spell slot level above base');
            $table->string('projectile_name', 50)->nullable()->after('projectile_per_level')
                ->comment('Display name for projectiles (dart, ray, beam, target)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('spell_effects', function (Blueprint $table) {
            $table->dropColumn(['projectile_count', 'projectile_per_level', 'projectile_name']);
        });
    }
};
