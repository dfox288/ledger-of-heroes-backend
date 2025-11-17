<?php

namespace Database\Factories;

use App\Models\Background;
use App\Models\SourceBook;
use Illuminate\Database\Eloquent\Factories\Factory;

class BackgroundFactory extends Factory
{
    protected $model = Background::class;

    public function definition(): array
    {
        $backgrounds = ['Acolyte', 'Charlatan', 'Criminal', 'Entertainer', 'Folk Hero', 'Guild Artisan', 'Hermit', 'Noble', 'Outlander', 'Sage', 'Sailor', 'Soldier', 'Urchin'];

        return [
            'name' => $this->faker->randomElement($backgrounds),
            'source_book_id' => SourceBook::inRandomOrder()->first()?->id ?? SourceBook::factory(),
            'source_page' => $this->faker->numberBetween(1, 350),
        ];
    }
}
