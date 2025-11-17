<?php

namespace Database\Factories;

use App\Models\SourceBook;
use Illuminate\Database\Eloquent\Factories\Factory;

class SourceBookFactory extends Factory
{
    protected $model = SourceBook::class;

    public function definition(): array
    {
        $books = [
            ['code' => 'PHB', 'name' => "Player's Handbook", 'abbreviation' => 'PHB'],
            ['code' => 'DMG', 'name' => "Dungeon Master's Guide", 'abbreviation' => 'DMG'],
            ['code' => 'MM', 'name' => 'Monster Manual', 'abbreviation' => 'MM'],
            ['code' => 'XGE', 'name' => "Xanathar's Guide to Everything", 'abbreviation' => 'XGE'],
        ];

        $book = $this->faker->randomElement($books);

        return [
            'code' => $book['code'],
            'name' => $book['name'],
            'abbreviation' => $book['abbreviation'],
            'release_date' => $this->faker->date(),
            'publisher' => 'Wizards of the Coast',
        ];
    }
}
