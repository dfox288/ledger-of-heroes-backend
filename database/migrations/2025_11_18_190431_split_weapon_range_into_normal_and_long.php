<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->unsignedInteger('range_normal')->nullable()->after('damage_type_id');
            $table->unsignedInteger('range_long')->nullable()->after('range_normal');
        });

        // Migrate existing data: split "50/150" into two columns (database-agnostic)
        $items = DB::table('items')->whereNotNull('weapon_range')->get();
        foreach ($items as $item) {
            if (str_contains($item->weapon_range, '/')) {
                [$normal, $long] = explode('/', $item->weapon_range, 2);
                DB::table('items')
                    ->where('id', $item->id)
                    ->update([
                        'range_normal' => (int) trim($normal),
                        'range_long' => (int) trim($long),
                    ]);
            }
        }

        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn('weapon_range');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->string('weapon_range', 50)->nullable();
        });

        // Restore original format (database-agnostic)
        $items = DB::table('items')->whereNotNull('range_normal')->whereNotNull('range_long')->get();
        foreach ($items as $item) {
            DB::table('items')
                ->where('id', $item->id)
                ->update([
                    'weapon_range' => $item->range_normal . '/' . $item->range_long,
                ]);
        }

        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn(['range_normal', 'range_long']);
        });
    }
};
