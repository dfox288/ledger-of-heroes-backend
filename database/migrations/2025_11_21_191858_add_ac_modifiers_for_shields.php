<?php

use App\Models\Item;
use App\Models\ItemType;
use App\Models\Modifier;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Add AC modifiers for all shields to match the pattern used by magic items.
     *
     * Strategy:
     * - Find all items with item_type = 'Shield' (code 'S')
     * - If armor_class > 0, create AC modifier with same value
     * - Keep armor_class column for backward compatibility
     * - Magic shields get additional modifier for enchantment bonus
     *
     * Example:
     * - Regular Shield: armor_class=2 + modifier(ac, 2)
     * - Shield +1: armor_class=2 + modifier(ac, 2) + modifier(ac, 1)
     */
    public function up(): void
    {
        // Get Shield item type
        $shieldType = ItemType::where('code', 'S')->first();

        if (! $shieldType) {
            // Silent skip - expected during test setup before seeders run
            return;
        }

        // Find all shield items with AC values
        $shields = Item::where('item_type_id', $shieldType->id)
            ->whereNotNull('armor_class')
            ->where('armor_class', '>', 0)
            ->get();

        echo "Found {$shields->count()} shields with AC values\n";

        $created = 0;
        $skipped = 0;

        foreach ($shields as $shield) {
            // Check if shield already has an AC modifier (from magic items)
            $existingModifiers = $shield->modifiers()
                ->where('modifier_category', 'ac')
                ->get();

            // For magic shields that already have modifiers, we need to add the BASE modifier
            // Example: Shield +1 has modifier(ac, 1) for magic, needs modifier(ac, 2) for base
            $hasBaseModifier = $existingModifiers->contains(function ($mod) use ($shield) {
                return $mod->value == $shield->armor_class;
            });

            if ($hasBaseModifier) {
                $skipped++;

                continue;
            }

            // Create base AC modifier (the inherent +2 from being a shield)
            Modifier::create([
                'reference_type' => Item::class,
                'reference_id' => $shield->id,
                'modifier_category' => 'ac',
                'value' => $shield->armor_class,
            ]);

            $created++;
        }

        echo "Migration complete: Created {$created} modifiers, Skipped {$skipped}\n";
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Get Shield item type
        $shieldType = ItemType::where('code', 'S')->first();

        if (! $shieldType) {
            return;
        }

        // Find all shield items
        $shieldIds = Item::where('item_type_id', $shieldType->id)->pluck('id');

        // Delete AC modifiers for shields where value matches armor_class column
        // This preserves magic modifiers (+1, +2, +3) but removes base shield modifiers
        DB::table('modifiers')
            ->where('reference_type', Item::class)
            ->whereIn('reference_id', $shieldIds)
            ->where('modifier_category', 'ac')
            ->whereRaw('value = (SELECT armor_class FROM items WHERE id = modifiers.reference_id)')
            ->delete();

        echo "Removed base AC modifiers from shields\n";
    }
};
