<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('feature-db')]
class LogoutTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function logout_invalidates_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        // Logout
        $logoutResponse = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/auth/logout');

        $logoutResponse->assertOk()
            ->assertJson([
                'message' => 'Logged out successfully',
            ]);

        // Clear auth guard cache to ensure fresh token validation
        $this->app['auth']->forgetGuards();

        // Token should no longer work
        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/auth/logout');

        $response->assertUnauthorized();
    }

    #[Test]
    public function logout_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/auth/logout');

        $response->assertUnauthorized();
    }

    #[Test]
    public function logout_with_invalid_token_returns_401(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer invalid-token')
            ->postJson('/api/v1/auth/logout');

        $response->assertUnauthorized();
    }

    #[Test]
    public function logout_only_revokes_current_token(): void
    {
        $user = User::factory()->create();
        $token1 = $user->createToken('token-1')->plainTextToken;
        $token2 = $user->createToken('token-2')->plainTextToken;

        // Logout with token1
        $this->withHeader('Authorization', 'Bearer '.$token1)
            ->postJson('/api/v1/auth/logout')
            ->assertOk();

        // Clear auth guard cache to ensure fresh token validation
        $this->app['auth']->forgetGuards();

        // Token1 should no longer work
        $this->withHeader('Authorization', 'Bearer '.$token1)
            ->postJson('/api/v1/auth/logout')
            ->assertUnauthorized();

        // Clear auth guard cache again for next request
        $this->app['auth']->forgetGuards();

        // Token2 should still work
        $this->withHeader('Authorization', 'Bearer '.$token2)
            ->postJson('/api/v1/auth/logout')
            ->assertOk();
    }
}
