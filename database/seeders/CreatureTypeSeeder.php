<?php

namespace Database\Seeders;

use App\Models\CreatureType;
use Illuminate\Database\Seeder;

class CreatureTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            [
                'slug' => 'core:aberration',
                'name' => 'Aberration',
                'description' => 'Utterly alien beings with bizarre anatomy, strange abilities, or alien mindsets.',
            ],
            [
                'slug' => 'core:beast',
                'name' => 'Beast',
                'description' => 'Nonhumanoid creatures that are part of the natural world.',
            ],
            [
                'slug' => 'core:celestial',
                'name' => 'Celestial',
                'description' => 'Creatures native to the Upper Planes, often agents of good.',
            ],
            [
                'slug' => 'core:construct',
                'name' => 'Construct',
                'typically_immune_to_poison' => true,
                'typically_immune_to_charmed' => true,
                'typically_immune_to_frightened' => true,
                'typically_immune_to_exhaustion' => true,
                'requires_sustenance' => false,
                'requires_sleep' => false,
                'description' => 'Made, not born. Animated by magic.',
            ],
            [
                'slug' => 'core:dragon',
                'name' => 'Dragon',
                'description' => 'Large reptilian creatures of ancient origin and tremendous power.',
            ],
            [
                'slug' => 'core:elemental',
                'name' => 'Elemental',
                'typically_immune_to_poison' => true,
                'typically_immune_to_exhaustion' => true,
                'requires_sustenance' => false,
                'requires_sleep' => false,
                'description' => 'Creatures native to the elemental planes.',
            ],
            [
                'slug' => 'core:fey',
                'name' => 'Fey',
                'description' => 'Magical creatures closely tied to the forces of nature.',
            ],
            [
                'slug' => 'core:fiend',
                'name' => 'Fiend',
                'typically_immune_to_poison' => true,
                'description' => 'Wicked creatures native to the Lower Planes.',
            ],
            [
                'slug' => 'core:giant',
                'name' => 'Giant',
                'description' => 'Humanlike creatures of great stature and strength.',
            ],
            [
                'slug' => 'core:humanoid',
                'name' => 'Humanoid',
                'description' => 'The main peoples of the world, including humans and their kin.',
            ],
            [
                'slug' => 'core:monstrosity',
                'name' => 'Monstrosity',
                'description' => 'Frightening creatures not ordinary, not truly natural, and not of divine origin.',
            ],
            [
                'slug' => 'core:ooze',
                'name' => 'Ooze',
                'typically_immune_to_poison' => true,
                'typically_immune_to_charmed' => true,
                'typically_immune_to_frightened' => true,
                'typically_immune_to_exhaustion' => true,
                'requires_sustenance' => false,
                'requires_sleep' => false,
                'description' => 'Gelatinous creatures that rarely have a fixed shape.',
            ],
            [
                'slug' => 'core:plant',
                'name' => 'Plant',
                'description' => 'Vegetable creatures, not ordinary flora.',
            ],
            [
                'slug' => 'core:undead',
                'name' => 'Undead',
                'typically_immune_to_poison' => true,
                'typically_immune_to_charmed' => true,
                'typically_immune_to_exhaustion' => true,
                'requires_sustenance' => false,
                'requires_sleep' => false,
                'description' => 'Once-living creatures brought to unlife through necromancy or curse.',
            ],
            [
                'slug' => 'core:swarm',
                'name' => 'Swarm',
                'description' => 'A mass of creatures acting as a single entity.',
            ],
        ];

        foreach ($types as $type) {
            CreatureType::updateOrCreate(
                ['slug' => $type['slug']],
                array_merge([
                    'typically_immune_to_poison' => false,
                    'typically_immune_to_charmed' => false,
                    'typically_immune_to_frightened' => false,
                    'typically_immune_to_exhaustion' => false,
                    'requires_sustenance' => true,
                    'requires_sleep' => true,
                ], $type)
            );
        }
    }
}
