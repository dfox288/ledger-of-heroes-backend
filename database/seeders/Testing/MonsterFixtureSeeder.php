<?php

namespace Database\Seeders\Testing;

use App\Models\DamageType;
use App\Models\EntitySource;
use App\Models\Modifier;
use App\Models\Monster;
use App\Models\Size;
use App\Models\Source;

class MonsterFixtureSeeder extends FixtureSeeder
{
    protected function fixturePath(): string
    {
        return 'tests/fixtures/entities/monsters.json';
    }

    protected function model(): string
    {
        return Monster::class;
    }

    protected function createFromFixture(array $item): void
    {
        // Resolve size by code
        $size = Size::where('code', $item['size'])->first();

        // Generate source-prefixed slug
        $slug = $item['slug'];
        if (! empty($item['source'])) {
            $sourceCode = strtolower($item['source']);
            $slug = $sourceCode.':'.$item['slug'];
        }

        // Create monster
        $monster = Monster::create([
            'name' => $item['name'],
            'slug' => $slug,
            'size_id' => $size?->id,
            'type' => $item['type'],
            'alignment' => $item['alignment'],
            'armor_class' => $item['armor_class'],
            'armor_type' => $item['armor_type'],
            'hit_points_average' => $item['hit_points'],
            'hit_dice' => $item['hit_dice'],
            'speed_walk' => $item['speed_walk'],
            'speed_fly' => $item['speed_fly'],
            'speed_swim' => $item['speed_swim'],
            'speed_burrow' => $item['speed_burrow'],
            'speed_climb' => $item['speed_climb'],
            'can_hover' => $item['can_hover'],
            'strength' => $item['strength'],
            'dexterity' => $item['dexterity'],
            'constitution' => $item['constitution'],
            'intelligence' => $item['intelligence'],
            'wisdom' => $item['wisdom'],
            'charisma' => $item['charisma'],
            'challenge_rating' => $item['challenge_rating'],
            'experience_points' => $item['experience_points'],
            'passive_perception' => $item['passive_perception'],
            'description' => $item['description'],
            'is_npc' => $item['is_npc'],
        ]);

        // Handle damage vulnerabilities
        if (! empty($item['damage_vulnerabilities'])) {
            foreach ($item['damage_vulnerabilities'] as $damageTypeCode) {
                $damageType = DamageType::where('code', $damageTypeCode)->first();
                if ($damageType) {
                    Modifier::create([
                        'reference_type' => Monster::class,
                        'reference_id' => $monster->id,
                        'modifier_category' => 'damage_vulnerability',
                        'damage_type_id' => $damageType->id,
                        'value' => '',
                    ]);
                }
            }
        }

        // Handle damage resistances
        if (! empty($item['damage_resistances'])) {
            foreach ($item['damage_resistances'] as $damageTypeCode) {
                $damageType = DamageType::where('code', $damageTypeCode)->first();
                if ($damageType) {
                    Modifier::create([
                        'reference_type' => Monster::class,
                        'reference_id' => $monster->id,
                        'modifier_category' => 'damage_resistance',
                        'damage_type_id' => $damageType->id,
                        'value' => '',
                    ]);
                }
            }
        }

        // Handle damage immunities
        if (! empty($item['damage_immunities'])) {
            foreach ($item['damage_immunities'] as $damageTypeCode) {
                $damageType = DamageType::where('code', $damageTypeCode)->first();
                if ($damageType) {
                    Modifier::create([
                        'reference_type' => Monster::class,
                        'reference_id' => $monster->id,
                        'modifier_category' => 'damage_immunity',
                        'damage_type_id' => $damageType->id,
                        'value' => '',
                    ]);
                }
            }
        }

        // Handle condition immunities
        if (! empty($item['condition_immunities'])) {
            foreach ($item['condition_immunities'] as $condition) {
                Modifier::create([
                    'reference_type' => Monster::class,
                    'reference_id' => $monster->id,
                    'modifier_category' => 'condition_immunity',
                    'value' => $condition,
                ]);
            }
        }

        // Create entity source (if source is provided)
        if (! empty($item['source'])) {
            $source = Source::where('code', $item['source'])->first();
            if ($source) {
                EntitySource::create([
                    'reference_type' => Monster::class,
                    'reference_id' => $monster->id,
                    'source_id' => $source->id,
                    'pages' => $item['pages'],
                ]);
            }
        }
    }
}
