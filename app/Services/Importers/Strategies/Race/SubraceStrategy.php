<?php

namespace App\Services\Importers\Strategies\Race;

use App\Models\Race;
use App\Models\Size;
use Illuminate\Support\Str;

class SubraceStrategy extends AbstractRaceStrategy
{
    /**
     * Subraces have a base_race_name but are not variants.
     */
    public function appliesTo(array $data): bool
    {
        return ! empty($data['base_race_name']) && empty($data['variant_of']);
    }

    /**
     * Enhance subrace data with parent resolution and compound slug.
     */
    public function enhance(array $data): array
    {
        $baseRaceName = $data['base_race_name'];
        $baseRaceSlug = Str::slug($baseRaceName);

        // Find or create base race
        $baseRace = Race::where('slug', $baseRaceSlug)->first();

        if (! $baseRace) {
            $baseRace = $this->createStubBaseRace($baseRaceName, $data);
            $this->incrementMetric('base_races_created');
        } else {
            $this->incrementMetric('base_races_resolved');
        }

        // Set parent_race_id
        $data['parent_race_id'] = $baseRace->id;

        // Generate compound slug (base-race-subrace)
        $data['slug'] = $baseRaceSlug.'-'.Str::slug($data['name']);

        // Track metric
        $this->incrementMetric('subraces_processed');

        return $data;
    }

    /**
     * Create a minimal stub base race when referenced by subrace.
     */
    private function createStubBaseRace(string $name, array $subraceData): Race
    {
        if (empty($subraceData['size_code'])) {
            $this->addWarning("Cannot create stub base race '{$name}': subrace missing size_code");

            return Race::factory()->make(['id' => 0]); // Return invalid stub
        }

        $size = Size::where('code', $subraceData['size_code'])->first();

        return Race::create([
            'name' => $name,
            'slug' => Str::slug($name),
            'size_id' => $size->id,
            'speed' => $subraceData['speed'] ?? 30,
            'description' => "Base race (auto-created from subrace '{$subraceData['name']}')",
        ]);
    }
}
