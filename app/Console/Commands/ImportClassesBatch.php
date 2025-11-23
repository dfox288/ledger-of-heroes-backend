<?php

namespace App\Console\Commands;

use App\Services\Importers\ClassImporter;
use App\Services\Importers\MergeMode;
use App\Services\Parsers\ClassXmlParser;
use Illuminate\Console\Command;

class ImportClassesBatch extends Command
{
    protected $signature = 'import:classes:batch
                            {pattern : Glob pattern for XML files (e.g., "import-files/class-barbarian-*.xml")}
                            {--merge : Use merge mode to add subclasses to existing classes}
                            {--skip-existing : Skip files if class already exists}';

    protected $description = 'Import multiple class XML files with merge strategy';

    public function handle(): int
    {
        $pattern = $this->argument('pattern');
        $files = glob(base_path($pattern));

        if (empty($files)) {
            $this->error("No files found matching pattern: {$pattern}");

            return self::FAILURE;
        }

        // Determine merge mode
        $mode = match (true) {
            $this->option('merge') => MergeMode::MERGE,
            $this->option('skip-existing') => MergeMode::SKIP_IF_EXISTS,
            default => MergeMode::CREATE,
        };

        $this->info('Importing '.count($files)." file(s) in {$mode->value} mode");
        $this->newLine();

        $parser = new ClassXmlParser;
        $importer = new ClassImporter;

        $totalClasses = 0;
        $totalSubclasses = 0;

        foreach ($files as $file) {
            $this->line('📄 '.basename($file));

            try {
                $xml = file_get_contents($file);
                $classes = $parser->parse($xml);

                foreach ($classes as $classData) {
                    $class = $importer->importWithMerge($classData, $mode);

                    $this->line("  ✓ {$class->name} ({$class->slug})");
                    $totalClasses++;

                    // Display subclasses
                    $subclasses = $class->subclasses;
                    if ($subclasses->isNotEmpty()) {
                        foreach ($subclasses as $subclass) {
                            $this->line("     ↳ {$subclass->name}");
                            $totalSubclasses++;
                        }
                    }
                }
            } catch (\Exception $e) {
                $this->error('  ✗ Failed: '.$e->getMessage());
            }

            $this->newLine();
        }

        // Summary
        $this->info('✅ Import complete!');
        $this->table(
            ['Type', 'Count'],
            [
                ['Files Processed', count($files)],
                ['Base Classes', $totalClasses],
                ['Subclasses', $totalSubclasses],
                ['Total', $totalClasses + $totalSubclasses],
            ]
        );

        return self::SUCCESS;
    }
}
