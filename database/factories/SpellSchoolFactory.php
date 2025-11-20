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
            ['code' => 'AB', 'name' => 'Abjuration', 'description' => 'Protective spells'],
            ['code' => 'CO', 'name' => 'Conjuration', 'description' => 'Summoning spells'],
            ['code' => 'DI', 'name' => 'Divination', 'description' => 'Information gathering spells'],
            ['code' => 'EN', 'name' => 'Enchantment', 'description' => 'Mind-affecting spells'],
            ['code' => 'EV', 'name' => 'Evocation', 'description' => 'Energy manipulation spells'],
            ['code' => 'IL', 'name' => 'Illusion', 'description' => 'Deception spells'],
            ['code' => 'NE', 'name' => 'Necromancy', 'description' => 'Death and undeath spells'],
            ['code' => 'TR', 'name' => 'Transmutation', 'description' => 'Transformation spells'],
        ];

        $school = $this->faker->randomElement($schools);

        return [
            'code' => $school['code'],
            'name' => $school['name'],
            'description' => $school['description'],
        ];
    }
}
