<?php

namespace Database\Factories;

use App\Models\SpellSchool;
use Illuminate\Database\Eloquent\Factories\Factory;

class SpellSchoolFactory extends Factory
{
    protected $model = SpellSchool::class;

    public function definition(): array
    {
        $schools = [
            ['code' => 'A', 'name' => 'Abjuration'],
            ['code' => 'C', 'name' => 'Conjuration'],
            ['code' => 'D', 'name' => 'Divination'],
            ['code' => 'E', 'name' => 'Enchantment'],
            ['code' => 'EV', 'name' => 'Evocation'],
            ['code' => 'I', 'name' => 'Illusion'],
            ['code' => 'N', 'name' => 'Necromancy'],
            ['code' => 'T', 'name' => 'Transmutation'],
        ];

        $school = $this->faker->randomElement($schools);

        return $school;
    }
}
