<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the sizes table with creature size categories.
 *
 * This seeder populates the six size categories used in D&D 5e:
 * Tiny, Small, Medium, Large, Huge, and Gargantuan.
 */
class SizeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('sizes')->insert([
            ['code' => 'T', 'name' => 'Tiny'],
            ['code' => 'S', 'name' => 'Small'],
            ['code' => 'M', 'name' => 'Medium'],
            ['code' => 'L', 'name' => 'Large'],
            ['code' => 'H', 'name' => 'Huge'],
            ['code' => 'G', 'name' => 'Gargantuan'],
        ]);
    }
}
