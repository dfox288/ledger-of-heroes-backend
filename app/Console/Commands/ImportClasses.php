<?php

namespace App\Console\Commands;

use App\Models\CharacterClass;
use App\Services\Importers\ClassImporter;
use App\Services\Parsers\ClassXmlParser;
use Illuminate\Console\Command;

class ImportClasses extends Command
{
    protected $signature = 'import:classes {file : Path to XML file}';

    protected $description = 'Import D&D classes from XML file';

    public function handle(): int
    {
        $filePath = $this->argument('file');

        // Validate file exists
        if (! file_exists($filePath)) {
            $this->error("File not found: {$filePath}");

            return self::FAILURE;
        }

        // Read XML content
        $this->info("Reading XML file: {$filePath}");
        $xmlContent = file_get_contents($filePath);

        try {
            // Parse XML
            $parser = new ClassXmlParser;
            $classes = $parser->parse($xmlContent);
            $this->info('Parsed '.count($classes).' class(es) from XML');

            // Import each class
            $importer = new ClassImporter;
            $baseClassCount = 0;
            $subclassCount = 0;

            foreach ($classes as $classData) {
                // Check if class already exists
                $existing = CharacterClass::where('name', $classData['name'])
                    ->whereNull('parent_class_id')
                    ->first();

                // Import the class (and its subclasses)
                $class = $importer->import($classData);

                // Display imported class
                $this->line("  → {$class->name} ({$class->slug})");

                // Count base class
                if (! $existing) {
                    $baseClassCount++;
                }

                // Count and display subclasses
                if (! empty($classData['subclasses'])) {
                    $subclassesImported = count($classData['subclasses']);
                    $subclassCount += $subclassesImported;

                    // Display each subclass
                    $subclasses = CharacterClass::where('parent_class_id', $class->id)->get();
                    foreach ($subclasses as $subclass) {
                        $this->line("     ↳ {$subclass->name} ({$subclass->slug})");
                    }
                }
            }

            $this->newLine();

            // Report results
            $this->info('✓ Import complete!');
            $this->table(
                ['Type', 'Count'],
                [
                    ['Base Class(es)', $baseClassCount],
                    ['Subclass(es)', $subclassCount],
                    ['Total', $baseClassCount + $subclassCount],
                ]
            );

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Import failed: '.$e->getMessage());
            $this->error($e->getTraceAsString());

            return self::FAILURE;
        }
    }
}
