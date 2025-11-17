<?php

namespace Database\Factories;

use App\Models\Size;
use Illuminate\Database\Eloquent\Factories\Factory;

class SizeFactory extends Factory
{
    protected $model = Size::class;

    public function definition(): array
    {
        $sizes = [
            ['code' => 'T', 'name' => 'Tiny'],
            ['code' => 'S', 'name' => 'Small'],
            ['code' => 'M', 'name' => 'Medium'],
            ['code' => 'L', 'name' => 'Large'],
            ['code' => 'H', 'name' => 'Huge'],
            ['code' => 'G', 'name' => 'Gargantuan'],
        ];

        $randomSize = $this->faker->randomElement($sizes);

        return [
            'code' => $randomSize['code'],
            'name' => $randomSize['name'],
        ];
    }
}
