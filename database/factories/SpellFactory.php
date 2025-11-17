<?php

namespace Database\Factories;

use App\Models\Spell;
use App\Models\SpellSchool;
use App\Models\SourceBook;
use Illuminate\Database\Eloquent\Factories\Factory;

class SpellFactory extends Factory
{
    protected $model = Spell::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->words(2, true),
            'level' => $this->faker->numberBetween(0, 9),
            'school_id' => SpellSchool::inRandomOrder()->first()?->id ?? SpellSchool::factory(),
            'is_ritual' => $this->faker->boolean(20),
            'casting_time' => '1 action',
            'range' => '60 feet',
            'duration' => 'Instantaneous',
            'has_verbal_component' => $this->faker->boolean(70),
            'has_somatic_component' => $this->faker->boolean(60),
            'has_material_component' => $this->faker->boolean(30),
            'material_description' => null,
            'material_cost_gp' => null,
            'material_consumed' => false,
            'description' => $this->faker->paragraph(),
            'source_book_id' => SourceBook::inRandomOrder()->first()?->id ?? SourceBook::factory(),
            'source_page' => $this->faker->numberBetween(1, 350),
        ];
    }
}
