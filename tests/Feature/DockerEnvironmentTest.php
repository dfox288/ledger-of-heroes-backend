<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DockerEnvironmentTest extends TestCase
{
    public function test_database_connection_works(): void
    {
        $this->assertTrue(DB::connection()->getPdo() !== null);
    }

    public function test_php_version_is_correct(): void
    {
        $this->assertGreaterThanOrEqual(8.4, (float) PHP_VERSION);
    }
}
