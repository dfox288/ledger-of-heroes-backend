<?php

namespace App\Services\Importers\Strategies\CharacterClass;

use App\Models\CharacterClass;
use Illuminate\Support\Str;

class SubclassStrategy extends AbstractClassStrategy
{
    /**
     * Subclasses have hit_die = 0 (supplemental data only).
     */
    public function appliesTo(array $data): bool
    {
        return ($data['hit_die'] ?? 0) === 0;
    }

    /**
     * Enhance subclass data with parent resolution and hit_die inheritance.
     */
    public function enhance(array $data): array
    {
        $parentName = $this->detectParentClassName($data['name']);
        $parent = CharacterClass::where('slug', Str::slug($parentName))->first();

        if ($parent) {
            $data['parent_class_id'] = $parent->id;
            $data['hit_die'] = $parent->hit_die;
            $data['slug'] = Str::slug($parentName).'-'.Str::slug($data['name']);

            $this->incrementMetric('parent_classes_resolved');
        } else {
            $this->addWarning("Parent class '{$parentName}' not found for subclass '{$data['name']}'");
            $data['parent_class_id'] = null;
            $data['slug'] = Str::slug($data['name']);
        }

        $this->incrementMetric('subclasses_processed');

        return $data;
    }

    /**
     * Detect parent class name from subclass name patterns.
     */
    private function detectParentClassName(string $subclassName): string
    {
        if (str_contains($subclassName, 'School of')) {
            return 'Wizard';
        }

        if (str_contains($subclassName, 'Oath of')) {
            return 'Paladin';
        }

        if (str_contains($subclassName, 'Circle of')) {
            return 'Druid';
        }

        if (str_contains($subclassName, 'Path of')) {
            return 'Barbarian';
        }

        if (str_contains($subclassName, 'College of')) {
            return 'Bard';
        }

        if (str_contains($subclassName, 'Domain')) {
            return 'Cleric';
        }

        if (str_contains($subclassName, 'Archetype')) {
            return 'Fighter';
        }

        if (str_contains($subclassName, 'Tradition')) {
            return 'Monk';
        }

        if (str_contains($subclassName, 'Conclave')) {
            return 'Ranger';
        }

        if (str_contains($subclassName, 'Patron')) {
            return 'Warlock';
        }

        if (str_contains($subclassName, 'Way of')) {
            return 'Rogue';
        }

        // Default: try to extract first word
        $words = explode(' ', $subclassName);

        return $words[0] ?? 'Unknown';
    }
}
