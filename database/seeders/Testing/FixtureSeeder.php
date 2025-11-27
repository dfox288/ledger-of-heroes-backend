<?php

namespace Database\Seeders\Testing;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

abstract class FixtureSeeder extends Seeder
{
    /**
     * Path to the JSON fixture file relative to base_path().
     */
    abstract protected function fixturePath(): string;

    /**
     * The model class this seeder populates.
     */
    abstract protected function model(): string;

    /**
     * Create a model instance from fixture data.
     */
    abstract protected function createFromFixture(array $item): void;

    /**
     * Run the seeder.
     */
    public function run(): void
    {
        $path = base_path($this->fixturePath());

        if (! File::exists($path)) {
            $this->command?->warn("Fixture file not found: {$this->fixturePath()}");

            return;
        }

        $data = json_decode(File::get($path), associative: true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->command?->error("Invalid JSON in {$this->fixturePath()}: ".json_last_error_msg());

            return;
        }

        foreach ($data as $item) {
            $this->createFromFixture($item);
        }

        $count = count($data);
        $model = class_basename($this->model());
        $this->command?->info("Seeded {$count} {$model} records from fixtures.");
    }
}
