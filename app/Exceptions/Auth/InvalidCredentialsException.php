<?php

namespace App\Exceptions\Auth;

use App\Exceptions\ApiException;
use Illuminate\Http\JsonResponse;

class InvalidCredentialsException extends ApiException
{
    public function __construct()
    {
        parent::__construct(
            message: 'Invalid email or password',
            code: 401
        );
    }

    public function render($request): JsonResponse
    {
        return response()->json([
            'message' => 'Invalid credentials',
            'error' => 'The provided email or password is incorrect.',
        ], 401);
    }
}
