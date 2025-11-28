---
name: m-implement-sanctum-auth
branch: feature/m-implement-sanctum-auth
status: pending
created: 2025-11-28
---

# Implement Laravel Sanctum Authentication

## Problem/Goal
Add API authentication to the D&D 5e importer application using Laravel Sanctum. This will enable token-based authentication for API consumers, allowing protected access to API endpoints.

## Success Criteria
- [ ] Sanctum package installed and configured
- [ ] User model configured with `HasApiTokens` trait
- [ ] Auth endpoints created (login, logout, optionally register)
- [ ] Protected API routes require valid token
- [ ] Token abilities/scopes defined if needed
- [ ] Tests cover authentication flow (login, protected routes, logout)
- [ ] API documentation updated for auth requirements

## Context Manifest

### How Authentication Currently Works (Or Doesn't)

**Current State: No Authentication Implemented**

This is a completely open, public API with zero authentication. All API routes in `/routes/api.php` are wide open - anyone can hit any endpoint without credentials. The only authorization check in the entire application is `return true;` in the `authorize()` methods of FormRequests (see `BaseIndexRequest.php` line 16 and `BaseShowRequest.php` line 15), which explicitly states "Public API" as the reason.

**Current Route Structure:**

All API routes live in `/routes/api.php` under a `Route::prefix('v1')` group. Laravel 12's new `bootstrap/app.php` architecture (lines 8-13) automatically:
- Prefixes these routes with `/api`
- Applies CORS middleware (`HandleCors::class` at line 16)
- Routes them through the `api` middleware group

The full URL pattern is: `/api/v1/{resource}` (e.g., `/api/v1/spells`, `/api/v1/monsters`)

There are 29+ API endpoints across:
- **Lookup endpoints** (under `/lookups/` prefix): sources, spell-schools, damage-types, sizes, ability-scores, skills, item-types, item-properties, conditions, proficiency-types, languages, tags, monster-types, alignments, armor-types, rarities, optional-feature-types
- **Entity endpoints** (root level): spells, races, backgrounds, items, feats, classes, monsters, optional-features
- **Global search**: `/search`

Every controller extends the minimal `Controller` base class which has no functionality - just an empty abstract class.

**User Model Status: Migration Exists, Model Missing**

The database migration exists (`0001_01_01_000000_create_users_table.php`) with a standard Laravel users table structure:
- `id` (bigint primary key)
- `name` (string)
- `email` (string, unique)
- `email_verified_at` (timestamp, nullable)
- `password` (string, hashed)
- `remember_token` (string)
- `timestamps` (created_at, updated_at)

The migration also creates:
- `password_reset_tokens` table (email, token, created_at)
- `sessions` table (id, user_id foreign key, ip_address, user_agent, payload, last_activity)

**However, there is NO `app/Models/User.php` file.** The application references it in `config/auth.php` line 65 (`App\Models\User::class`) but the file doesn't exist. This will need to be created from scratch.

**Auth Configuration:**

`config/auth.php` is stock Laravel 12:
- Default guard: `web` (session-based, line 17)
- Guards defined: Only `web` guard exists (lines 38-42), using session driver
- User provider: Eloquent using `App\Models\User::class` (lines 62-66)
- No `sanctum` guard configured yet

**No Factory for User Either:**

There's no `UserFactory.php` in `/database/factories/`. The directory contains 35 other factories (Background, CharacterClass, Spell, Monster, etc.) but no User factory. This will need to be created for testing.

**Exception Handling Architecture:**

The application has a custom exception hierarchy:
- Base: `App\Exceptions\ApiException` (abstract, requires `render()` method)
- Search exceptions: `SearchException`, `InvalidFilterSyntaxException` (in `/app/Exceptions/Search/`)
- Import exceptions: (in `/app/Exceptions/Import/`)
- Lookup exceptions: (in `/app/Exceptions/Lookup/`)

All custom exceptions extend `ApiException` and implement `render($request): JsonResponse` to return consistent JSON error responses. The bootstrap/app.php (line 31) registers a renderable callback for all `ApiException` instances.

This pattern should be followed for authentication exceptions (e.g., `UnauthenticatedException`, `InvalidTokenException`).

### What Needs to Be Built: Sanctum Token-Based Authentication

**Package Status:**

Laravel Sanctum is **NOT** installed. `composer.json` shows:
- Laravel 12 framework
- Scout + Meilisearch for search
- Scramble for OpenAPI docs
- Spatie tags
- **No Sanctum package**

First step: `composer require laravel/sanctum`

**Architecture Decisions to Make:**

1. **Token Abilities/Scopes Strategy:**
   - Option A: Simple auth (single token type, no scopes) - easier, sufficient for basic API
   - Option B: Scoped tokens (read-only vs write access) - more complex, better for multi-client scenarios
   - Recommendation: Start simple (Option A) since API is currently read-only

2. **Protected vs Public Routes:**
   - Current: ALL routes public
   - Decision needed: Which routes require auth?
     - Likely candidates for protection: None initially (it's a reference API)
     - Or: Keep public for reads, add auth for future write endpoints
   - Recommendation: Add auth infrastructure but keep all current routes public, prepare for future protected endpoints

3. **User Registration Strategy:**
   - Option A: Public registration (`POST /api/v1/auth/register`)
   - Option B: Admin-only user creation (no registration endpoint)
   - Option C: Invitation-based registration
   - Recommendation: Based on project context (D&D reference API), likely Option A for developer access

**Implementation Components Needed:**

**1. User Model (`app/Models/User.php`):**
```php
namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password'];
    protected $hidden = ['password', 'remember_token'];
    protected $casts = ['email_verified_at' => 'datetime', 'password' => 'hashed'];
}
```

**Important:** This should extend `Authenticatable`, NOT `BaseModel`. BaseModel disables timestamps (`$timestamps = false`), but users table needs timestamps. BaseModel is for static D&D reference data.

**2. Auth Controller (`app/Http/Controllers/Api/AuthController.php`):**

Following the controller pattern in this app:
- Extends `App\Http\Controllers\Controller` (base class)
- Methods: `login()`, `logout()`, optionally `register()`
- Uses Form Requests for validation
- Returns JSON responses (no views, this is API-only)
- Comprehensive PHPDoc for Scramble documentation

**3. Form Requests:**

Following the naming convention `{Entity}{Action}Request`:
- `LoginRequest` - validates email/password (extends FormRequest, not BaseIndexRequest)
- `RegisterRequest` - validates name/email/password/password_confirmation
- Pattern: Put in `app/Http/Requests/` (NOT in Api subdirectory)

These won't extend `BaseIndexRequest` or `BaseShowRequest` since auth doesn't fit the resource pattern. They'll extend FormRequest directly with custom rules.

**4. API Resources (if needed):**

- `UserResource` - formats user data for responses (optional, could just return raw)
- Pattern: `app/Http/Resources/UserResource.php`

**5. Auth Routes (`routes/api.php`):**

Add OUTSIDE the `v1` prefix group (or inside, decision needed):
```php
Route::prefix('v1')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/register', [AuthController::class, 'register']); // optional

    // Protected routes (require Sanctum auth)
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        // Future: protected entity endpoints here
    });

    // Existing public routes...
});
```

**6. Config Updates:**

Add to `config/auth.php` guards array:
```php
'guards' => [
    'web' => [...],
    'sanctum' => [
        'driver' => 'sanctum',
        'provider' => 'users',
    ],
],
```

Publish Sanctum config: `php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"`

**7. Sanctum Middleware:**

Laravel 12 uses the new bootstrap/app.php architecture. Sanctum middleware needs to be added to the API middleware group in `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->api(prepend: [
        \Illuminate\Http\Middleware\HandleCors::class,
    ]);

    // Add stateful domains if needed for SPA
    $middleware->statefulApi();
})
```

**8. Migration for Personal Access Tokens:**

Sanctum requires a `personal_access_tokens` table. After `composer require`, run:
```bash
php artisan vendor:publish --tag=sanctum-migrations
php artisan migrate
```

### Test Strategy (TDD Mandatory)

**Test Suite Assignment:**

Based on phpunit.xml structure, auth tests belong in **Feature-DB suite** (not Feature-Search):
- Tests API endpoints
- Uses database (users table)
- No Meilisearch search functionality
- Fits with other API tests in Feature-DB

**Test Files Needed:**

1. **`tests/Feature/Auth/LoginTest.php`:**
   - Test successful login returns token
   - Test invalid credentials return 401
   - Test validation errors (missing email, malformed email, missing password)
   - Test token can be used to access protected routes

2. **`tests/Feature/Auth/LogoutTest.php`:**
   - Test logout invalidates token
   - Test logout requires authentication
   - Test using token after logout fails

3. **`tests/Feature/Auth/RegisterTest.php`** (if implementing):
   - Test successful registration creates user and returns token
   - Test duplicate email rejected
   - Test password confirmation required
   - Test validation rules

4. **`tests/Feature/Auth/ProtectedRoutesTest.php`:**
   - Test protected routes require valid token
   - Test invalid token returns 401
   - Test missing token returns 401
   - Test revoked token returns 401

**Test Pattern to Follow:**

Based on `SpellApiTest.php`:
```php
namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    protected $seeder = \Database\Seeders\LookupSeeder::class; // Only lookup data

    #[Test]
    public function successful_login_returns_token()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'token',
                'user' => ['id', 'name', 'email'],
            ]);
    }

    // More tests...
}
```

**UserFactory Pattern:**

Following existing factory conventions in `/database/factories/`:
```php
namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => Hash::make('password'), // Default test password
            'remember_token' => Str::random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
```

### API Documentation (Scramble Integration)

**How Scramble Works in This App:**

Scramble auto-generates OpenAPI documentation from:
- Controller method PHPDoc blocks (see `SpellController` lines 22-95 for gold standard)
- FormRequest validation rules
- API Resource structures
- Route definitions

Docs are served at `http://localhost:8080/docs/api` (config in `config/scramble.php`)

**PHPDoc Pattern for Auth Endpoints:**

Following the verbose documentation style in `SpellController`:

```php
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
 * @return \Illuminate\Http\JsonResponse
 */
public function login(LoginRequest $request)
{
    // Implementation...
}
```

### Technical Reference

#### File Paths for Implementation

**Models:**
- Create: `/Users/dfox/Development/dnd/importer/app/Models/User.php`

**Controllers:**
- Create: `/Users/dfox/Development/dnd/importer/app/Http/Controllers/Api/AuthController.php`

**Form Requests:**
- Create: `/Users/dfox/Development/dnd/importer/app/Http/Requests/LoginRequest.php`
- Create: `/Users/dfox/Development/dnd/importer/app/Http/Requests/RegisterRequest.php` (optional)

**API Resources:**
- Create: `/Users/dfox/Development/dnd/importer/app/Http/Resources/UserResource.php` (optional)

**Routes:**
- Modify: `/Users/dfox/Development/dnd/importer/routes/api.php`

**Factories:**
- Create: `/Users/dfox/Development/dnd/importer/database/factories/UserFactory.php`

**Tests:**
- Create: `/Users/dfox/Development/dnd/importer/tests/Feature/Auth/LoginTest.php`
- Create: `/Users/dfox/Development/dnd/importer/tests/Feature/Auth/LogoutTest.php`
- Create: `/Users/dfox/Development/dnd/importer/tests/Feature/Auth/RegisterTest.php`
- Create: `/Users/dfox/Development/dnd/importer/tests/Feature/Auth/ProtectedRoutesTest.php`

**Config:**
- Modify: `/Users/dfox/Development/dnd/importer/config/auth.php` (add sanctum guard)
- Modify: `/Users/dfox/Development/dnd/importer/bootstrap/app.php` (potentially for middleware)

**Documentation:**
- Update: `/Users/dfox/Development/dnd/importer/docs/TODO.md` (mark task in progress/complete)
- Update: `/Users/dfox/Development/dnd/importer/CHANGELOG.md` (under [Unreleased])

#### Environment Configuration

Add to `.env`:
```env
SANCTUM_STATEFUL_DOMAINS=localhost,localhost:8080,127.0.0.1,127.0.0.1:8080
```

#### Commands to Run

**Installation:**
```bash
composer require laravel/sanctum
php artisan vendor:publish --tag=sanctum-migrations
php artisan migrate
php artisan vendor:publish --tag=sanctum-config
```

**Testing:**
```bash
# Run Feature-DB suite (includes auth tests)
docker compose exec php php artisan test --testsuite=Feature-DB

# Format code
docker compose exec php ./vendor/bin/pint
```

#### Sanctum Token Response Structure

```json
{
  "token": "1|abc123def456...",
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "user@example.com"
  }
}
```

#### Error Response Pattern

Following existing exception pattern (`InvalidFilterSyntaxException`):

```php
namespace App\Exceptions\Auth;

use App\Exceptions\ApiException;
use Illuminate\Http\JsonResponse;

class InvalidCredentialsException extends ApiException
{
    public function __construct()
    {
        parent::__construct(
            message: "Invalid email or password",
            code: 401
        );
    }

    public function render($request): JsonResponse
    {
        return response()->json([
            'message' => 'Invalid credentials',
            'error' => 'The provided email or password is incorrect',
        ], 401);
    }
}
```

### Critical Architectural Notes

**1. Don't Use BaseModel for User:**

All D&D entity models extend `BaseModel` which disables timestamps. User must NOT do this - it needs `created_at`/`updated_at` for security auditing and Laravel's authentication system expects them.

**2. Laravel 12 Middleware Registration:**

Laravel 12 replaced `app/Http/Kernel.php` with `bootstrap/app.php`. Middleware is registered via the `withMiddleware()` callback, not in a Kernel class.

**3. Test Isolation:**

Tests use `RefreshDatabase` trait and in-memory SQLite (see phpunit.xml line 162: `DB_DATABASE=:memory:`). Each test gets a fresh database. The `LookupSeeder` is run before each test (via `$seeder` property) to populate lookup tables (spell schools, damage types, etc.) but NOT entity data.

**4. TDD is Non-Negotiable:**

From CLAUDE.md: "Tests written FIRST", "Implementation code written before tests" = REJECTED. This is emphasized multiple times. Write all auth tests before implementing any auth logic.

**5. FormRequest Validation Pattern:**

All controllers use dedicated FormRequest classes for validation. Never validate directly in controllers. Each action gets its own Request class following the pattern `{Entity}{Action}Request`.

**6. PHPUnit 11 Attributes:**

Use `#[Test]` attribute syntax, not `@test` doc comments (deprecated in PHPUnit 11). See line 6 of `SpellApiTest.php`.

**7. Docker Commands:**

All artisan and Composer commands run inside Docker:
```bash
docker compose exec php php artisan ...
docker compose exec php composer ...
docker compose exec php ./vendor/bin/pint
```

### Decision Points for Implementation

**1. Route Versioning:**
- Auth endpoints inside `/api/v1/auth/*` (consistent with existing v1 structure)
- OR at `/api/auth/*` (version-independent auth)
- **Recommendation:** Inside v1 for consistency

**2. Token Naming:**
- Use `$user->createToken('api-token')` - generic
- OR `$user->createToken('API Access Token')` - descriptive
- **Recommendation:** Descriptive for better token management UI later

**3. Token Abilities:**
- Start with single token type (no abilities)
- OR implement read/write abilities from start
- **Recommendation:** No abilities initially, add later if needed

**4. Registration Endpoint:**
- Include now (developer self-service)
- OR skip (admin-only user creation)
- **Recommendation:** Include - it's a reference API, developers should self-register

**5. Email Verification:**
- Require email verification (more secure)
- OR skip verification (easier onboarding)
- **Recommendation:** Skip initially, add later if abuse becomes issue

### Success Criteria Checklist

- [ ] Laravel Sanctum package installed via Composer
- [ ] `personal_access_tokens` migration run
- [ ] `User` model created with `HasApiTokens` trait
- [ ] `UserFactory` created for testing
- [ ] `AuthController` created with login/logout methods
- [ ] `LoginRequest` and optionally `RegisterRequest` created
- [ ] Auth routes registered in `routes/api.php`
- [ ] `sanctum` guard added to `config/auth.php`
- [ ] All auth tests written FIRST (LoginTest, LogoutTest, ProtectedRoutesTest, optionally RegisterTest)
- [ ] Tests pass in Feature-DB suite
- [ ] Code formatted with Pint
- [ ] Scramble documentation shows auth endpoints
- [ ] CHANGELOG.md updated
- [ ] TODO.md updated

## User Notes
<!-- Any specific notes or requirements from the developer -->

## Work Log
<!-- Updated as work progresses -->
- [2025-11-28] Task created
