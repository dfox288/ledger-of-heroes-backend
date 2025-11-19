<?php

use App\Models\AbilityScore;
use App\Models\EntityPrerequisite;
use App\Models\Item;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Migrate existing items.strength_requirement values to structured entity_prerequisites.
     * This migration is idempotent and safe to run multiple times.
     */
    public function up(): void
    {
        // Get STR ability score (should always exist from seeder)
        $strAbilityScore = AbilityScore::where('code', 'STR')->first();

        if (! $strAbilityScore) {
            // If STR doesn't exist, skip migration (should never happen in prod)
            return;
        }

        // Get all items with strength requirements
        $items = Item::whereNotNull('strength_requirement')
            ->where('strength_requirement', '>', 0)
            ->get();

        foreach ($items as $item) {
            // Check if prerequisite already exists (idempotency)
            $exists = EntityPrerequisite::where('reference_type', Item::class)
                ->where('reference_id', $item->id)
                ->where('prerequisite_type', AbilityScore::class)
                ->where('prerequisite_id', $strAbilityScore->id)
                ->exists();

            if (! $exists) {
                EntityPrerequisite::create([
                    'reference_type' => Item::class,
                    'reference_id' => $item->id,
                    'prerequisite_type' => AbilityScore::class,
                    'prerequisite_id' => $strAbilityScore->id,
                    'minimum_value' => $item->strength_requirement,
                    'description' => null,
                    'group_id' => 1,
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * Note: We don't delete the entity_prerequisites records on rollback
     * because the legacy strength_requirement column is preserved.
     */
    public function down(): void
    {
        // Remove prerequisites created by this migration
        $strAbilityScore = AbilityScore::where('code', 'STR')->first();

        if ($strAbilityScore) {
            EntityPrerequisite::where('reference_type', Item::class)
                ->where('prerequisite_type', AbilityScore::class)
                ->where('prerequisite_id', $strAbilityScore->id)
                ->delete();
        }
    }
};
