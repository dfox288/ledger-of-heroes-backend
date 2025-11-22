<?php

namespace Database\Factories;

use App\Models\ProficiencyType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProficiencyType>
 */
class ProficiencyTypeFactory extends Factory
{
    protected $model = ProficiencyType::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $categories = ['weapon', 'armor', 'tool', 'skill', 'language', 'saving_throw'];
        $category = fake()->randomElement($categories);

        $subcategories = [
            'weapon' => ['simple', 'martial'],
            'armor' => ['light', 'medium', 'heavy'],
            'tool' => ['artisan', 'gaming', 'musical'],
        ];

        $name = fake()->words(2, true);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'category' => $category,
            'subcategory' => isset($subcategories[$category]) ? fake()->randomElement($subcategories[$category]) : null,
            'item_id' => null,
        ];
    }
}
