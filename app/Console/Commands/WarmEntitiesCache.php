<?php

namespace App\Console\Commands;

use App\Models\Background;
use App\Models\CharacterClass;
use App\Models\Feat;
use App\Models\Item;
use App\Models\Monster;
use App\Models\Race;
use App\Models\Spell;
use App\Services\Cache\EntityCacheService;
use Illuminate\Console\Command;

class WarmEntitiesCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cache:warm-entities {--type=* : Entity types to warm (spell, item, monster, class, race, background, feat)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Pre-warm entity caches for faster API responses';

    /**
     * Execute the console command.
     */
    public function handle(EntityCacheService $cache): int
    {
        $types = $this->option('type') ?: ['spell', 'item', 'monster', 'class', 'race', 'background', 'feat'];

        $this->info('Warming entity caches...');

        $totalCached = 0;

        foreach ($types as $type) {
            try {
                $count = $this->warmEntityType($type, $cache);
                $this->info("✓ {$type}s cached ({$count} entities)");
                $totalCached += $count;
            } catch (\InvalidArgumentException $e) {
                $this->error("✗ Error: {$e->getMessage()}");

                return Command::FAILURE;
            }
        }

        $this->newLine();
        $this->info('All entity caches warmed successfully!');
        $this->info("Total: {$totalCached} entities cached with 15-minute TTL");

        return Command::SUCCESS;
    }

    /**
     * Warm cache for a specific entity type
     */
    private function warmEntityType(string $type, EntityCacheService $cache): int
    {
        $modelClass = $this->getModelClass($type);
        $method = 'get'.ucfirst($type);

        $ids = $modelClass::pluck('id');

        foreach ($ids as $id) {
            $cache->$method($id);
        }

        return $ids->count();
    }

    /**
     * Get the model class for an entity type
     */
    private function getModelClass(string $type): string
    {
        return match ($type) {
            'spell' => Spell::class,
            'item' => Item::class,
            'monster' => Monster::class,
            'class' => CharacterClass::class,
            'race' => Race::class,
            'background' => Background::class,
            'feat' => Feat::class,
            default => throw new \InvalidArgumentException("Unknown entity type: {$type}"),
        };
    }
}
