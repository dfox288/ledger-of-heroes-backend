<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds equipment_slot column to items table for paperdoll UI.
     * Maps items to body slots (head, neck, cloak, armor, clothes, belt, hands, feet, ring, hand).
     * Nullable for items that don't equip to body slots (potions, scrolls, bags, etc.).
     *
     * @see https://github.com/dfox288/ledger-of-heroes/issues/589
     */
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->string('equipment_slot', 20)->nullable()->after('recharge_timing');
            $table->index('equipment_slot');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropIndex(['equipment_slot']);
            $table->dropColumn('equipment_slot');
        });
    }
};
