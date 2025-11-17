<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ImportAllCommand extends Command
{
    protected $signature = 'import:all {directory=import-files : Directory containing XML files}';
    protected $description = 'Import all D&D 5e content from XML files';

    public function handle(): int
    {
        $directory = $this->argument('directory');

        $this->info("Importing all content from: {$directory}");

        if (!is_dir($directory)) {
            $this->error("Error: Directory not found: {$directory}");
            return self::FAILURE;
        }

        $importCommands = [
            ['command' => 'import:spells', 'pattern' => 'spells-*.xml', 'name' => 'spells'],
            ['command' => 'import:items', 'pattern' => 'items-*.xml', 'name' => 'items'],
            ['command' => 'import:races', 'pattern' => 'races-*.xml', 'name' => 'races'],
            ['command' => 'import:backgrounds', 'pattern' => 'backgrounds-*.xml', 'name' => 'backgrounds'],
            ['command' => 'import:feats', 'pattern' => 'feats-*.xml', 'name' => 'feats'],
        ];

        $totalFiles = 0;
        $failedFiles = 0;

        foreach ($importCommands as $import) {
            $files = glob("{$directory}/{$import['pattern']}");

            if (empty($files)) {
                $this->warn("No {$import['name']} files found matching pattern: {$import['pattern']}");
                continue;
            }

            $this->newLine();
            $this->info("Processing {$import['name']}...");

            foreach ($files as $file) {
                $totalFiles++;
                $exitCode = $this->call($import['command'], ['file' => $file]);

                if ($exitCode !== self::SUCCESS) {
                    $failedFiles++;
                    $this->error("  Failed to import: {$file}");
                }
            }
        }

        $this->newLine();
        $this->info('Import complete!');
        $this->info("Total files processed: {$totalFiles}");

        if ($failedFiles > 0) {
            $this->warn("Failed imports: {$failedFiles}");
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
