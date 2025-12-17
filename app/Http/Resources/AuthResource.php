<?php

namespace App\Http\Resources;

use App\DTOs\AuthResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource for authentication responses (login/register).
 *
 * Note: This resource uses $wrap = null because the auth response
 * does not follow the standard "data" envelope pattern. The token
 * is returned at the root level for convenience in client apps.
 *
 * @mixin AuthResult
 */
class AuthResource extends JsonResource
{
    /**
     * Disable data wrapping for this resource.
     *
     * @var string|null
     */
    public static $wrap = null;

    /**
     * Transform the resource into an array.
     *
     * @return array{token: string, user: array{id: int, name: string, email: string}}
     */
    public function toArray(Request $request): array
    {
        return [
            'token' => $this->token,
            'user' => (new UserResource($this->user))->resolve($request),
        ];
    }
}
