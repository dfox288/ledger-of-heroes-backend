<?php

namespace App\Console\Commands;

use App\Services\Search\MeilisearchIndexConfigurator;
use Illuminate\Console\Command;
use MeiliSearch\Client;

class ConfigureMeilisearchIndexes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'search:configure-indexes';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Configure Meilisearch index settings';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Configuring Meilisearch indexes...');

        try {
            $client = new Client(
                config('scout.meilisearch.host'),
                config('scout.meilisearch.key')
            );
            $configurator = new MeilisearchIndexConfigurator($client);

            $this->info('Configuring spells index...');
            $configurator->configureSpellsIndex();
            $this->info('✓ Spells index configured successfully');

            $this->newLine();
            $this->info('All indexes configured successfully!');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed to configure indexes: '.$e->getMessage());

            return Command::FAILURE;
        }
    }
}
