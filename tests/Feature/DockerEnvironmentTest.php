<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DockerEnvironmentTest extends TestCase
{
    public function test_database_connection_works(): void
    {
        $connection = DB::connection();
        $this->assertNotNull($connection->getPdo());

        // Force actual query to verify connection
        $result = $connection->select('SELECT 1 as test');
        $this->assertNotEmpty($result);
    }

    public function test_php_version_is_correct(): void
    {
        $this->assertTrue(
            version_compare(PHP_VERSION, '8.4.0', '>='),
            "PHP version must be >= 8.4.0, found: " . PHP_VERSION
        );
    }
}
