<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ClassSubclassAudit\InventoryReportGenerator;
use App\Services\ClassSubclassAudit\SubclassInventoryService;
use Illuminate\Console\Command;

/**
 * Audit all classes and subclasses for data completeness.
 *
 * Generates an inventory of features, spells, proficiencies, and counters
 * for every subclass in the database.
 *
 * @example php artisan audit:class-subclass-matrix
 * @example php artisan audit:class-subclass-matrix --class=phb:fighter
 * @example php artisan audit:class-subclass-matrix --export=json --detailed
 * @example php artisan audit:class-subclass-matrix --issues-only
 */
class AuditClassSubclassMatrixCommand extends Command
{
    protected $signature = 'audit:class-subclass-matrix
        {--class= : Filter to a specific base class (e.g., phb:fighter)}
        {--subclass= : Filter to a specific subclass (e.g., phb:fighter-battle-master)}
        {--detailed : Show detailed feature/spell lists}
        {--export=json : Export to JSON file}
        {--issues-only : Only show potential issues}';

    protected $description = 'Audit class/subclass data completeness for all classes';

    public function handle(
        SubclassInventoryService $inventoryService,
        InventoryReportGenerator $reportGenerator
    ): int {
        $this->info('Class/Subclass Matrix Audit');
        $this->info('===========================');
        $this->newLine();

        // Get inventory (filtered if class option provided)
        $inventory = $this->getInventory($inventoryService);

        if (empty($inventory['classes'])) {
            $this->error('No classes found matching criteria.');

            return Command::FAILURE;
        }

        // Issues-only mode
        if ($this->option('issues-only')) {
            return $this->displayIssuesOnly($inventory, $reportGenerator);
        }

        // Generate console output
        $lines = $reportGenerator->generateConsoleOutput(
            $inventory,
            (bool) $this->option('detailed')
        );

        foreach ($lines as $line) {
            $this->line($line);
        }

        // Find and display issues
        $this->newLine();
        $issues = $reportGenerator->findIssues($inventory);

        if (! empty($issues)) {
            $this->warn('Potential Issues Found:');
            $this->table(
                ['Subclass', 'Issue', 'Severity'],
                array_map(fn ($i) => [$i['subclass'], $i['issue'], $i['severity']], $issues)
            );
        } else {
            $this->info('No obvious issues detected.');
        }

        // Export to JSON if requested
        if ($this->option('export') === 'json') {
            $path = $reportGenerator->saveJson($inventory);
            $this->newLine();
            $this->info("Exported to: {$path}");
        }

        return Command::SUCCESS;
    }

    /**
     * Get inventory, optionally filtered by class.
     */
    private function getInventory(SubclassInventoryService $service): array
    {
        $classFilter = $this->option('class');
        $subclassFilter = $this->option('subclass');

        // Get full inventory first
        $inventory = $service->getFullInventory();

        // Filter by specific subclass
        if ($subclassFilter) {
            $filtered = ['classes' => [], 'summary' => $inventory['summary'], 'generated_at' => $inventory['generated_at']];

            foreach ($inventory['classes'] as $classSlug => $classData) {
                if (isset($classData['subclasses'][$subclassFilter])) {
                    $filtered['classes'][$classSlug] = [
                        'name' => $classData['name'],
                        'subclass_count' => 1,
                        'subclass_level' => $classData['subclass_level'],
                        'is_spellcaster' => $classData['is_spellcaster'],
                        'subclasses' => [$subclassFilter => $classData['subclasses'][$subclassFilter]],
                    ];
                    $filtered['summary']['subclasses'] = 1;

                    return $filtered;
                }
            }

            return $filtered;
        }

        // Filter by base class
        if ($classFilter) {
            if (! isset($inventory['classes'][$classFilter])) {
                return ['classes' => [], 'summary' => ['base_classes' => 0, 'subclasses' => 0, 'issues_flagged' => 0], 'generated_at' => now()->toIso8601String()];
            }

            return [
                'generated_at' => $inventory['generated_at'],
                'summary' => [
                    'base_classes' => 1,
                    'subclasses' => $inventory['classes'][$classFilter]['subclass_count'],
                    'issues_flagged' => 0,
                ],
                'classes' => [
                    $classFilter => $inventory['classes'][$classFilter],
                ],
            ];
        }

        return $inventory;
    }

    /**
     * Display only issues, no full inventory.
     */
    private function displayIssuesOnly(array $inventory, InventoryReportGenerator $reportGenerator): int
    {
        $issues = $reportGenerator->findIssues($inventory);

        if (empty($issues)) {
            $this->info('No issues detected across all classes/subclasses.');

            return Command::SUCCESS;
        }

        $this->warn('Issues Found: '.count($issues));
        $this->newLine();

        // Group by severity
        $highSeverity = array_filter($issues, fn ($i) => $i['severity'] === 'high');
        $mediumSeverity = array_filter($issues, fn ($i) => $i['severity'] === 'medium');

        if (! empty($highSeverity)) {
            $this->error('HIGH SEVERITY ('.count($highSeverity).'):');
            $this->table(
                ['Subclass', 'Issue'],
                array_map(fn ($i) => [$i['subclass'], $i['issue']], $highSeverity)
            );
            $this->newLine();
        }

        if (! empty($mediumSeverity)) {
            $this->warn('MEDIUM SEVERITY ('.count($mediumSeverity).'):');
            $this->table(
                ['Subclass', 'Issue'],
                array_map(fn ($i) => [$i['subclass'], $i['issue']], $mediumSeverity)
            );
        }

        return Command::FAILURE;
    }
}
