<?php

namespace App\DTOs;

use App\Models\User;

/**
 * Data Transfer Object for authentication responses.
 */
class AuthResult
{
    public function __construct(
        public readonly string $token,
        public readonly User $user,
    ) {}
}
