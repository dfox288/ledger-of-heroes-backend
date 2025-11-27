<?php

namespace Database\Seeders\Testing;

use App\Models\CharacterClass;
use App\Models\DamageType;
use App\Models\EntitySource;
use App\Models\Source;
use App\Models\Spell;
use App\Models\SpellEffect;
use App\Models\SpellSchool;

class SpellFixtureSeeder extends FixtureSeeder
{
    protected function fixturePath(): string
    {
        return 'tests/fixtures/entities/spells.json';
    }

    protected function model(): string
    {
        return Spell::class;
    }

    protected function createFromFixture(array $item): void
    {
        // Resolve spell school by code
        $spellSchool = SpellSchool::where('code', $item['school'])->first();

        // Convert components array to comma-separated string
        $components = is_array($item['components'])
            ? implode(', ', $item['components'])
            : $item['components'];

        // Create spell
        $spell = Spell::create([
            'name' => $item['name'],
            'slug' => $item['slug'],
            'level' => $item['level'],
            'spell_school_id' => $spellSchool?->id,
            'casting_time' => $item['casting_time'],
            'range' => $item['range'],
            'components' => $components,
            'material_components' => $item['material_components'],
            'duration' => $item['duration'],
            'needs_concentration' => $item['needs_concentration'],
            'is_ritual' => $item['is_ritual'],
            'description' => $item['description'],
            'higher_levels' => $item['higher_levels'],
        ]);

        // Attach classes
        if (! empty($item['classes'])) {
            $classIds = CharacterClass::whereIn('slug', $item['classes'])->pluck('id');
            $spell->classes()->attach($classIds);
        }

        // Create spell effects for damage types
        if (! empty($item['damage_types'])) {
            foreach ($item['damage_types'] as $damageTypeCode) {
                $damageType = DamageType::where('code', $damageTypeCode)->first();
                if ($damageType) {
                    SpellEffect::create([
                        'spell_id' => $spell->id,
                        'effect_type' => 'damage',
                        'damage_type_id' => $damageType->id,
                    ]);
                }
            }
        }

        // Create entity sources
        if (! empty($item['sources'])) {
            foreach ($item['sources'] as $sourceData) {
                $source = Source::where('code', $sourceData['code'])->first();
                if ($source) {
                    EntitySource::create([
                        'reference_type' => Spell::class,
                        'reference_id' => $spell->id,
                        'source_id' => $source->id,
                        'pages' => $sourceData['pages'],
                    ]);
                }
            }
        }
    }
}
