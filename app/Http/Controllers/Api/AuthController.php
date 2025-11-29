<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\Auth\InvalidCredentialsException;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Authenticate user and issue API token
     *
     * Validates user credentials and returns a Sanctum personal access token for API authentication.
     * The token should be included in subsequent requests as a Bearer token in the Authorization header.
     *
     * **Usage:**
     * ```
     * POST /api/v1/auth/login
     * Content-Type: application/json
     *
     * {
     *   "email": "user@example.com",
     *   "password": "your-password"
     * }
     *
     * Response:
     * {
     *   "token": "1|abc123...",
     *   "user": {
     *     "id": 1,
     *     "name": "John Doe",
     *     "email": "user@example.com"
     *   }
     * }
     * ```
     *
     * **Using the Token:**
     * ```
     * GET /api/v1/protected-endpoint
     * Authorization: Bearer 1|abc123...
     * ```
     *
     * @param  LoginRequest  $request  Validated login credentials
     *
     * @throws InvalidCredentialsException
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw new InvalidCredentialsException;
        }

        $token = $user->createToken('API Access Token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
    }

    /**
     * Register a new user
     *
     * Creates a new user account and returns a Sanctum personal access token for immediate API access.
     * The token should be included in subsequent requests as a Bearer token in the Authorization header.
     *
     * **Usage:**
     * ```
     * POST /api/v1/auth/register
     * Content-Type: application/json
     *
     * {
     *   "name": "John Doe",
     *   "email": "user@example.com",
     *   "password": "password123",
     *   "password_confirmation": "password123"
     * }
     *
     * Response (201 Created):
     * {
     *   "token": "1|abc123...",
     *   "user": {
     *     "id": 1,
     *     "name": "John Doe",
     *     "email": "user@example.com"
     *   }
     * }
     * ```
     *
     * **Validation Requirements:**
     * - `name`: Required, max 255 characters
     * - `email`: Required, valid email format, unique in users table
     * - `password`: Required, minimum 8 characters, must be confirmed
     * - `password_confirmation`: Required, must match password
     *
     * @param  RegisterRequest  $request  Validated registration data
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $token = $user->createToken('API Access Token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ], 201);
    }

    /**
     * Logout and revoke current token
     *
     * Revokes the current API token used for authentication. Other tokens for the same user remain valid.
     * This endpoint requires authentication.
     *
     * **Usage:**
     * ```
     * POST /api/v1/auth/logout
     * Authorization: Bearer 1|abc123...
     *
     * Response:
     * {
     *   "message": "Logged out successfully"
     * }
     * ```
     *
     * **Note:** Only the token used to make this request is revoked. If the user has multiple tokens
     * (e.g., logged in from multiple devices), those other tokens remain valid.
     *
     * @param  Request  $request  Request with authenticated user
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully',
        ]);
    }
}
