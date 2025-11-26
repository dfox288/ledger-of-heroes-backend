<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('feature-db')]
class CorsTest extends TestCase
{
    public function test_api_returns_cors_headers(): void
    {
        $response = $this->withHeaders([
            'Origin' => 'http://localhost:3000',
            'Access-Control-Request-Method' => 'GET',
        ])->options('/api/test');

        $response->assertStatus(204)
            ->assertHeader('Access-Control-Allow-Origin', '*')
            ->assertHeader('Access-Control-Allow-Methods');
    }
}
