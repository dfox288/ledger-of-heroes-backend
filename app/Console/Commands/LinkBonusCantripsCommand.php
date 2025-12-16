<?php

namespace App\Console\Commands;

use App\Models\ClassFeature;
use App\Models\EntitySpell;
use App\Models\Spell;
use Illuminate\Console\Command;

/**
 * Links bonus cantrips to class features after spells are imported.
 *
 * Issue #683: Due to import order (classes before spells), bonus cantrips
 * like Light Domain's "you gain the light cantrip" aren't linked during
 * initial class import because the spells don't exist yet.
 *
 * This command is called by import:all after spells are imported.
 */
class LinkBonusCantripsCommand extends Command
{
    protected $signature = 'import:link-bonus-cantrips';

    protected $description = 'Link bonus cantrips to class features (post-import processing)';

    public function handle(): int
    {
        $this->info('Linking bonus cantrips to class features...');

        // Pattern: "you (gain|learn) the X cantrip(s)"
        $pattern = '/you (?:gain|learn) the ([a-z][a-z\s]+?) cantrips?/i';

        // Find features with bonus cantrip text
        $features = ClassFeature::where('description', 'like', '%you gain the%cantrip%')
            ->orWhere('description', 'like', '%you learn the%cantrip%')
            ->get();

        $linkedCount = 0;
        $skippedCount = 0;
        $notFoundSpells = [];

        foreach ($features as $feature) {
            if (! preg_match($pattern, $feature->description, $matches)) {
                continue;
            }

            $spellNamesText = trim($matches[1]);

            // Parse multiple cantrips: "sacred flame and light" -> ["sacred flame", "light"]
            $spellNames = preg_split('/\s+and\s+/', $spellNamesText);
            $spellNames = array_map('trim', $spellNames);
            $spellNames = array_filter($spellNames);

            foreach ($spellNames as $spellName) {
                // Check if already linked
                $existingLink = EntitySpell::where('reference_type', ClassFeature::class)
                    ->where('reference_id', $feature->id)
                    ->whereHas('spell', fn ($q) => $q->whereRaw('LOWER(name) = ?', [strtolower($spellName)]))
                    ->exists();

                if ($existingLink) {
                    $skippedCount++;

                    continue;
                }

                $spell = Spell::whereRaw('LOWER(name) = ?', [strtolower($spellName)])->first();

                if (! $spell) {
                    $notFoundSpells[$spellName] = $notFoundSpells[$spellName] ?? 0;
                    $notFoundSpells[$spellName]++;

                    continue;
                }

                // Use updateOrCreate to prevent duplicates
                EntitySpell::updateOrCreate(
                    [
                        'reference_type' => ClassFeature::class,
                        'reference_id' => $feature->id,
                        'spell_id' => $spell->id,
                    ],
                    [
                        'is_cantrip' => true,
                        'level_requirement' => null,
                    ]
                );
                $linkedCount++;
            }
        }

        $this->info("  ✓ Linked {$linkedCount} bonus cantrip(s) ({$skippedCount} already linked)");

        if (! empty($notFoundSpells)) {
            $this->warn('  Spells not found: '.implode(', ', array_keys($notFoundSpells)));
        }

        return self::SUCCESS;
    }
}
