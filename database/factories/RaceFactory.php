<?php

namespace Database\Factories;

use App\Models\Race;
use App\Models\Size;
use App\Models\SourceBook;
use Illuminate\Database\Eloquent\Factories\Factory;

class RaceFactory extends Factory
{
    protected $model = Race::class;

    public function definition(): array
    {
        $races = ['Elf', 'Dwarf', 'Human', 'Halfling', 'Dragonborn', 'Gnome', 'Half-Elf', 'Half-Orc', 'Tiefling'];

        return [
            'name' => $this->faker->randomElement($races),
            'size_id' => Size::inRandomOrder()->first()?->id ?? Size::factory(),
            'speed' => $this->faker->randomElement([25, 30, 35]),
            'source_book_id' => SourceBook::inRandomOrder()->first()?->id ?? SourceBook::factory(),
            'source_page' => $this->faker->numberBetween(1, 350),
        ];
    }
}
