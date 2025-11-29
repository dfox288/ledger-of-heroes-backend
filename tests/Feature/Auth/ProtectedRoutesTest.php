<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('feature-db')]
class ProtectedRoutesTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function protected_route_requires_valid_token(): void
    {
        $response = $this->postJson('/api/v1/auth/logout');

        $response->assertUnauthorized();
    }

    #[Test]
    public function protected_route_rejects_invalid_token(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer invalid-token-here')
            ->postJson('/api/v1/auth/logout');

        $response->assertUnauthorized();
    }

    #[Test]
    public function protected_route_rejects_missing_bearer_prefix(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        // Send token without 'Bearer ' prefix
        $response = $this->withHeader('Authorization', $token)
            ->postJson('/api/v1/auth/logout');

        $response->assertUnauthorized();
    }

    #[Test]
    public function protected_route_accepts_valid_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/auth/logout');

        $response->assertOk();
    }

    #[Test]
    public function revoked_token_cannot_access_protected_routes(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        // Revoke all tokens
        $user->tokens()->delete();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/auth/logout');

        $response->assertUnauthorized();
    }

    #[Test]
    public function public_routes_remain_accessible_without_token(): void
    {
        // Login and register endpoints should remain public
        $loginResponse = $this->postJson('/api/v1/auth/login', []);

        // Should get validation error (422), not unauthorized (401)
        $loginResponse->assertUnprocessable();

        $registerResponse = $this->postJson('/api/v1/auth/register', []);

        // Should get validation error (422), not unauthorized (401)
        $registerResponse->assertUnprocessable();
    }
}
