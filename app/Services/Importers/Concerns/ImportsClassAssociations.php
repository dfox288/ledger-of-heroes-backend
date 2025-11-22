<?php

namespace App\Services\Importers\Concerns;

use App\Models\CharacterClass;
use Illuminate\Database\Eloquent\Model;

trait ImportsClassAssociations
{
    /**
     * Subclass name aliases for XML → Database mapping.
     *
     * XML files use abbreviated/variant names that differ from official subclass names.
     * This map handles special cases where fuzzy matching won't work.
     *
     * Format: 'XML Name' => 'Database Name'
     */
    private const SUBCLASS_ALIASES = [
        // Druid Circle of the Land variants (Coast, Desert, Forest, etc. are terrain options, not separate subclasses)
        'Coast' => 'Circle of the Land',
        'Desert' => 'Circle of the Land',
        'Forest' => 'Circle of the Land',
        'Grassland' => 'Circle of the Land',
        'Mountain' => 'Circle of the Land',
        'Swamp' => 'Circle of the Land',
        'Underdark' => 'Circle of the Land',
        'Arctic' => 'Circle of the Land',

        // Common abbreviations
        'Ancients' => 'Oath of the Ancients',
        'Vengeance' => 'Oath of Vengeance',
    ];

    /**
     * Sync class associations (replaces existing).
     *
     * @param  Model  $entity  Entity with classes() relationship (Spell, Background, etc.)
     * @param  array  $classNames  Class names from XML (may include subclasses in parentheses)
     */
    public function syncClassAssociations(Model $entity, array $classNames): void
    {
        $classIds = $this->resolveClassIds($classNames);
        $entity->classes()->sync($classIds);
    }

    /**
     * Add class associations (merges with existing).
     *
     * @param  Model  $entity  Entity with classes() relationship
     * @param  array  $classNames  Class names to add
     * @return int Number of new associations added
     */
    public function addClassAssociations(Model $entity, array $classNames): int
    {
        $newClassIds = $this->resolveClassIds($classNames);
        $existingClassIds = $entity->classes()->pluck('class_id')->toArray();
        $allClassIds = array_unique(array_merge($existingClassIds, $newClassIds));

        $entity->classes()->sync($allClassIds);

        return count($allClassIds) - count($existingClassIds);
    }

    /**
     * Resolve array of class names to class IDs.
     *
     * @param  array  $classNames  Class names from XML
     * @return array Array of class IDs
     */
    private function resolveClassIds(array $classNames): array
    {
        $classIds = [];

        foreach ($classNames as $className) {
            $class = $this->resolveClassFromName($className);

            if ($class) {
                $classIds[] = $class->id;
            }
        }

        return $classIds;
    }

    /**
     * Resolve a single class name to CharacterClass model.
     *
     * Logic:
     * - "Fighter (Eldritch Knight)" → Use SUBCLASS (Eldritch Knight)
     * - "Wizard" → Use BASE CLASS (Wizard)
     *
     * @param  string  $className  Class name from XML
     * @return CharacterClass|null The resolved class, or null if not found
     */
    private function resolveClassFromName(string $className): ?CharacterClass
    {
        // Check if subclass is specified in parentheses: "Fighter (Eldritch Knight)"
        if (preg_match('/^(.+?)\s*\(([^)]+)\)$/', $className, $matches)) {
            $baseClassName = trim($matches[1]);
            $subclassName = trim($matches[2]);

            // Check if there's an alias mapping for this subclass name
            if (isset(self::SUBCLASS_ALIASES[$subclassName])) {
                $subclassName = self::SUBCLASS_ALIASES[$subclassName];
            }

            // Try to find the SUBCLASS - try exact match first, then fuzzy match
            $class = CharacterClass::where('name', $subclassName)->first();

            // If exact match fails, try fuzzy match (e.g., "Archfey" -> "The Archfey")
            if (! $class) {
                $class = CharacterClass::where('name', 'LIKE', "%{$subclassName}%")->first();
            }

            return $class;
        }

        // No parentheses = use base class
        return CharacterClass::where('name', $className)
            ->whereNull('parent_class_id') // Only match base classes
            ->first();
    }
}
