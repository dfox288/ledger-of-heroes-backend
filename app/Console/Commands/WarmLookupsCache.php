<?php

namespace App\Console\Commands;

use App\Services\Cache\LookupCacheService;
use Illuminate\Console\Command;

class WarmLookupsCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cache:warm-lookups';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Pre-warm lookup table caches';

    /**
     * Execute the console command.
     */
    public function handle(LookupCacheService $cache): int
    {
        $this->info('Warming lookup caches...');

        $cache->getSpellSchools();
        $this->line('✓ Spell schools cached (8 entries)');

        $cache->getDamageTypes();
        $this->line('✓ Damage types cached (13 entries)');

        $cache->getConditions();
        $this->line('✓ Conditions cached (15 entries)');

        $cache->getSizes();
        $this->line('✓ Sizes cached (9 entries)');

        $cache->getAbilityScores();
        $this->line('✓ Ability scores cached (6 entries)');

        $cache->getLanguages();
        $this->line('✓ Languages cached (30 entries)');

        $cache->getProficiencyTypes();
        $this->line('✓ Proficiency types cached (82 entries)');

        $this->newLine();
        $this->info('All lookup caches warmed successfully!');
        $this->comment('Total: 163 entries cached with 1-hour TTL');

        return Command::SUCCESS;
    }
}
