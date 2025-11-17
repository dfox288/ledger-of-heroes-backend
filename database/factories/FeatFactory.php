<?php

namespace Database\Factories;

use App\Models\Feat;
use App\Models\SourceBook;
use Illuminate\Database\Eloquent\Factories\Factory;

class FeatFactory extends Factory
{
    protected $model = Feat::class;

    public function definition(): array
    {
        $feats = ['Alert', 'Athlete', 'Actor', 'Charger', 'Crossbow Expert', 'Defensive Duelist', 'Dual Wielder', 'Dungeon Delver', 'Durable', 'Great Weapon Master', 'Healer', 'Lucky', 'Mobile', 'Polearm Master', 'Resilient', 'Sentinel', 'Sharpshooter', 'War Caster'];

        return [
            'name' => $this->faker->randomElement($feats),
            'description' => $this->faker->paragraph(3),
            'source_book_id' => SourceBook::inRandomOrder()->first()?->id ?? SourceBook::factory(),
            'source_page' => $this->faker->numberBetween(1, 350),
        ];
    }
}
