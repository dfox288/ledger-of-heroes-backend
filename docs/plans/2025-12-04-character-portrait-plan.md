# Implementation Plan: Character Portrait (#120)

**Design Document:** `docs/plans/2025-12-04-character-portrait-design.md`
**Branch:** `feature/issue-120-character-portrait`
**Runner:** Sail (`docker compose exec php ...`)

---

## Phase 1: Scaffolding

### Task 1.1: Create feature branch
```bash
git checkout -b feature/issue-120-character-portrait
```

### Task 1.2: Install spatie/laravel-medialibrary
```bash
docker compose exec php composer require spatie/laravel-medialibrary
```

### Task 1.3: Publish medialibrary migrations
```bash
docker compose exec php php artisan vendor:publish --provider="Spatie\MediaLibrary\MediaLibraryServiceProvider" --tag="medialibrary-migrations"
```

### Task 1.4: Run migrations
```bash
docker compose exec php php artisan migrate
```

### Task 1.5: Create storage symlink (if not exists)
```bash
docker compose exec php php artisan storage:link
```

**Commit:** `chore(#120): Install spatie/laravel-medialibrary package`

---

## Phase 2: Configuration

### Task 2.1: Create config/media.php

**File:** `config/media.php`

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Media-Enabled Models
    |--------------------------------------------------------------------------
    |
    | Registry of models that support media uploads via the polymorphic
    | MediaController. Each entry defines the model class and allowed
    | media collections.
    |
    */
    'models' => [
        'characters' => [
            'class' => \App\Models\Character::class,
            'collections' => ['portrait', 'token'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Upload Constraints
    |--------------------------------------------------------------------------
    */
    'max_file_size' => 2048, // 2MB in KB

    'accepted_mimetypes' => [
        'image/jpeg',
        'image/png',
        'image/webp',
    ],
];
```

**Commit:** `feat(#120): Add media configuration for polymorphic uploads`

---

## Phase 3: Data Model

### Task 3.1: Create migration for portrait_url column

**File:** `database/migrations/YYYY_MM_DD_HHMMSS_add_portrait_url_to_characters_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->string('portrait_url', 2048)->nullable()->after('asi_choices_remaining');
        });
    }

    public function down(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->dropColumn('portrait_url');
        });
    }
};
```

```bash
docker compose exec php php artisan migrate
```

### Task 3.2: Update Character model with HasMedia

**File:** `app/Models/Character.php`

Add imports:
```php
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
```

Change class declaration:
```php
class Character extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;
```

Add to `$fillable`:
```php
'portrait_url',
```

Add methods:
```php
public function registerMediaCollections(): void
{
    $this->addMediaCollection('portrait')
        ->singleFile()
        ->acceptsMimeTypes(config('media.accepted_mimetypes'));

    $this->addMediaCollection('token')
        ->singleFile()
        ->acceptsMimeTypes(config('media.accepted_mimetypes'));
}

public function registerMediaConversions(?Media $media = null): void
{
    $this->addMediaConversion('thumb')
        ->width(150)
        ->height(150)
        ->performOnCollections('portrait', 'token');

    $this->addMediaConversion('medium')
        ->width(300)
        ->height(300)
        ->performOnCollections('portrait', 'token');
}
```

### Task 3.3: Update CharacterFactory (optional portrait_url)

**File:** `database/factories/CharacterFactory.php`

No changes needed - `portrait_url` defaults to null.

**Commit:** `feat(#120): Add HasMedia to Character model with portrait/token collections`

---

## Phase 4: Form Requests

### Task 4.1: Create MediaUploadRequest

**File:** `app/Http/Requests/Media/MediaUploadRequest.php`

```php
<?php

namespace App\Http\Requests\Media;

use Illuminate\Foundation\Http\FormRequest;

class MediaUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $maxSize = config('media.max_file_size', 2048);
        $mimetypes = implode(',', config('media.accepted_mimetypes', []));

        return [
            'file' => [
                'required',
                'file',
                'image',
                "max:{$maxSize}",
                "mimetypes:{$mimetypes}",
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'A file is required.',
            'file.image' => 'The file must be an image.',
            'file.max' => 'The image must not exceed 2MB.',
            'file.mimetypes' => 'The image must be a JPEG, PNG, or WebP.',
        ];
    }
}
```

### Task 4.2: Update CharacterUpdateRequest

**File:** `app/Http/Requests/Character/CharacterUpdateRequest.php`

Add to rules array:
```php
'portrait_url' => ['nullable', 'url', 'max:2048'],
```

**Commit:** `feat(#120): Add MediaUploadRequest and portrait_url validation`

---

## Phase 5: API Resources

### Task 5.1: Create MediaResource

**File:** `app/Http/Resources/MediaResource.php`

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MediaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'collection' => $this->collection_name,
            'file_name' => $this->file_name,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'urls' => [
                'original' => $this->getUrl(),
                'thumb' => $this->hasGeneratedConversion('thumb')
                    ? $this->getUrl('thumb')
                    : null,
                'medium' => $this->hasGeneratedConversion('medium')
                    ? $this->getUrl('medium')
                    : null,
            ],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
```

### Task 5.2: Update CharacterResource with portrait data

**File:** `app/Http/Resources/CharacterResource.php`

Add to `toArray()` return array (after `updated_at`):
```php
'portrait' => $this->getPortraitData(),
```

Add private method:
```php
/**
 * Get portrait data from uploaded media or external URL.
 */
private function getPortraitData(): ?array
{
    // Check for uploaded media first
    if ($this->relationLoaded('media')) {
        $media = $this->getFirstMedia('portrait');
        if ($media) {
            return [
                'original' => $media->getUrl(),
                'thumb' => $media->hasGeneratedConversion('thumb')
                    ? $media->getUrl('thumb')
                    : null,
                'medium' => $media->hasGeneratedConversion('medium')
                    ? $media->getUrl('medium')
                    : null,
                'is_uploaded' => true,
            ];
        }
    }

    // Fall back to external URL
    if ($this->portrait_url) {
        return [
            'original' => $this->portrait_url,
            'thumb' => null,
            'medium' => null,
            'is_uploaded' => false,
        ];
    }

    return null;
}
```

**Commit:** `feat(#120): Add MediaResource and portrait data to CharacterResource`

---

## Phase 6: Controller

### Task 6.1: Create MediaController

**File:** `app/Http/Controllers/Api/MediaController.php`

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Media\MediaUploadRequest;
use App\Http\Resources\MediaResource;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Spatie\MediaLibrary\HasMedia;

/**
 * Polymorphic Media Controller
 *
 * Handles media uploads for any model registered in config/media.php.
 * Supports portrait images for characters with automatic thumbnail generation.
 *
 * @group Media
 */
class MediaController extends Controller
{
    /**
     * List media in a collection
     *
     * Returns all media items in the specified collection for the model.
     *
     * **Example:**
     * ```
     * GET /api/v1/characters/5/media/portrait
     * ```
     */
    public function index(Request $request, string $modelType, int $modelId, string $collection)
    {
        $model = $this->resolveModel($modelType, $modelId);
        $this->validateCollection($modelType, $collection);

        return MediaResource::collection($model->getMedia($collection));
    }

    /**
     * Upload media to a collection
     *
     * Uploads an image file to the specified collection. For single-file collections
     * like 'portrait', this replaces any existing file.
     *
     * **Example:**
     * ```
     * POST /api/v1/characters/5/media/portrait
     * Content-Type: multipart/form-data
     * file: [binary image data]
     * ```
     *
     * **Validation:**
     * - Max size: 2MB
     * - Allowed types: JPEG, PNG, WebP
     */
    public function store(MediaUploadRequest $request, string $modelType, int $modelId, string $collection)
    {
        $model = $this->resolveModel($modelType, $modelId);
        $this->validateCollection($modelType, $collection);

        $media = $model->addMediaFromRequest('file')
            ->toMediaCollection($collection);

        return (new MediaResource($media))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Delete media from a collection
     *
     * Removes media from the collection. If mediaId is provided, deletes only that
     * specific media item. Otherwise, clears the entire collection.
     *
     * **Examples:**
     * ```
     * DELETE /api/v1/characters/5/media/portrait           # Clear collection
     * DELETE /api/v1/characters/5/media/portrait/123       # Delete specific media
     * ```
     */
    public function destroy(Request $request, string $modelType, int $modelId, string $collection, ?int $mediaId = null)
    {
        $model = $this->resolveModel($modelType, $modelId);
        $this->validateCollection($modelType, $collection);

        if ($mediaId) {
            $media = $model->media()->where('id', $mediaId)->firstOrFail();
            $media->delete();
        } else {
            $model->clearMediaCollection($collection);
        }

        return response()->noContent();
    }

    /**
     * Resolve model from type and ID using config registry.
     */
    private function resolveModel(string $type, int $id): HasMedia
    {
        $config = config("media.models.{$type}");

        abort_unless($config, 404, "Unknown model type: {$type}");

        return $config['class']::findOrFail($id);
    }

    /**
     * Validate that collection is allowed for this model type.
     */
    private function validateCollection(string $modelType, string $collection): void
    {
        $allowed = config("media.models.{$modelType}.collections", []);

        abort_unless(
            in_array($collection, $allowed),
            422,
            "Collection '{$collection}' not allowed for {$modelType}"
        );
    }
}
```

**Commit:** `feat(#120): Add polymorphic MediaController`

---

## Phase 7: Routes

### Task 7.1: Add media routes to api.php

**File:** `routes/api.php`

Add import at top:
```php
use App\Http\Controllers\Api\MediaController;
```

Add routes (inside the Route::prefix('v1') group, after character routes):
```php
/*
|--------------------------------------------------------------------------
| Polymorphic Media Routes
|--------------------------------------------------------------------------
|
| Generic media upload/delete endpoints for any model registered in
| config/media.php. Used for character portraits, tokens, and future
| entity media attachments.
|
*/
Route::prefix('{modelType}/{modelId}/media')->group(function () {
    Route::get('{collection}', [MediaController::class, 'index'])
        ->name('media.index');
    Route::post('{collection}', [MediaController::class, 'store'])
        ->name('media.store');
    Route::delete('{collection}', [MediaController::class, 'destroy'])
        ->name('media.destroy');
    Route::delete('{collection}/{mediaId}', [MediaController::class, 'destroy'])
        ->name('media.destroyOne');
});
```

**Commit:** `feat(#120): Add polymorphic media routes`

---

## Phase 8: Tests (TDD)

### Task 8.1: Write failing tests for MediaController

**File:** `tests/Feature/Api/MediaControllerTest.php`

```php
<?php

namespace Tests\Feature\Api;

use App\Models\Character;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_uploads_portrait_to_character(): void
    {
        $character = Character::factory()->create();
        $file = UploadedFile::fake()->image('portrait.jpg', 400, 400);

        $response = $this->postJson("/api/v1/characters/{$character->id}/media/portrait", [
            'file' => $file,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'collection',
                    'file_name',
                    'mime_type',
                    'size',
                    'urls' => ['original', 'thumb', 'medium'],
                    'created_at',
                ],
            ]);

        $this->assertDatabaseHas('media', [
            'model_type' => Character::class,
            'model_id' => $character->id,
            'collection_name' => 'portrait',
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_lists_media_in_collection(): void
    {
        $character = Character::factory()->create();
        $character->addMedia(UploadedFile::fake()->image('portrait.jpg'))
            ->toMediaCollection('portrait');

        $response = $this->getJson("/api/v1/characters/{$character->id}/media/portrait");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_deletes_portrait_collection(): void
    {
        $character = Character::factory()->create();
        $character->addMedia(UploadedFile::fake()->image('portrait.jpg'))
            ->toMediaCollection('portrait');

        $response = $this->deleteJson("/api/v1/characters/{$character->id}/media/portrait");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('media', [
            'model_id' => $character->id,
            'collection_name' => 'portrait',
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_deletes_specific_media_by_id(): void
    {
        $character = Character::factory()->create();
        $media = $character->addMedia(UploadedFile::fake()->image('portrait.jpg'))
            ->toMediaCollection('portrait');

        $response = $this->deleteJson("/api/v1/characters/{$character->id}/media/portrait/{$media->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('media', ['id' => $media->id]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_rejects_invalid_model_type(): void
    {
        $response = $this->postJson('/api/v1/invalid/1/media/portrait', [
            'file' => UploadedFile::fake()->image('portrait.jpg'),
        ]);

        $response->assertStatus(404);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_rejects_invalid_collection(): void
    {
        $character = Character::factory()->create();

        $response = $this->postJson("/api/v1/characters/{$character->id}/media/invalid", [
            'file' => UploadedFile::fake()->image('portrait.jpg'),
        ]);

        $response->assertStatus(422);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_rejects_file_exceeding_max_size(): void
    {
        $character = Character::factory()->create();
        // Create file larger than 2MB (2048 KB)
        $file = UploadedFile::fake()->create('large.jpg', 3000, 'image/jpeg');

        $response = $this->postJson("/api/v1/characters/{$character->id}/media/portrait", [
            'file' => $file,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('file');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_rejects_invalid_mime_type(): void
    {
        $character = Character::factory()->create();
        $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

        $response = $this->postJson("/api/v1/characters/{$character->id}/media/portrait", [
            'file' => $file,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('file');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_404_for_nonexistent_character(): void
    {
        $response = $this->postJson('/api/v1/characters/99999/media/portrait', [
            'file' => UploadedFile::fake()->image('portrait.jpg'),
        ]);

        $response->assertStatus(404);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_replaces_existing_portrait_on_single_file_collection(): void
    {
        $character = Character::factory()->create();

        // Upload first portrait
        $character->addMedia(UploadedFile::fake()->image('first.jpg'))
            ->toMediaCollection('portrait');

        // Upload second portrait
        $response = $this->postJson("/api/v1/characters/{$character->id}/media/portrait", [
            'file' => UploadedFile::fake()->image('second.jpg'),
        ]);

        $response->assertStatus(201);

        // Should only have one media item
        $this->assertEquals(1, $character->fresh()->getMedia('portrait')->count());
    }
}
```

### Task 8.2: Write tests for CharacterResource portrait data

**File:** `tests/Feature/Api/CharacterResourcePortraitTest.php`

```php
<?php

namespace Tests\Feature\Api;

use App\Models\Character;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CharacterResourcePortraitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_includes_uploaded_portrait_in_character_response(): void
    {
        $character = Character::factory()->create();
        $character->addMedia(UploadedFile::fake()->image('portrait.jpg', 400, 400))
            ->toMediaCollection('portrait');

        $response = $this->getJson("/api/v1/characters/{$character->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'portrait' => [
                        'original',
                        'thumb',
                        'medium',
                        'is_uploaded',
                    ],
                ],
            ])
            ->assertJsonPath('data.portrait.is_uploaded', true);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_includes_external_url_portrait_in_character_response(): void
    {
        $character = Character::factory()->create([
            'portrait_url' => 'https://example.com/my-portrait.jpg',
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.portrait.original', 'https://example.com/my-portrait.jpg')
            ->assertJsonPath('data.portrait.thumb', null)
            ->assertJsonPath('data.portrait.medium', null)
            ->assertJsonPath('data.portrait.is_uploaded', false);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_null_portrait_when_none_set(): void
    {
        $character = Character::factory()->create();

        $response = $this->getJson("/api/v1/characters/{$character->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.portrait', null);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function uploaded_portrait_takes_precedence_over_external_url(): void
    {
        $character = Character::factory()->create([
            'portrait_url' => 'https://example.com/external.jpg',
        ]);
        $character->addMedia(UploadedFile::fake()->image('uploaded.jpg'))
            ->toMediaCollection('portrait');

        $response = $this->getJson("/api/v1/characters/{$character->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.portrait.is_uploaded', true);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_set_portrait_url_via_patch(): void
    {
        $character = Character::factory()->create();

        $response = $this->patchJson("/api/v1/characters/{$character->id}", [
            'portrait_url' => 'https://example.com/new-portrait.jpg',
        ]);

        $response->assertStatus(200);
        $this->assertEquals(
            'https://example.com/new-portrait.jpg',
            $character->fresh()->portrait_url
        );
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_validates_portrait_url_format(): void
    {
        $character = Character::factory()->create();

        $response = $this->patchJson("/api/v1/characters/{$character->id}", [
            'portrait_url' => 'not-a-valid-url',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('portrait_url');
    }
}
```

### Task 8.3: Run tests (expect failures initially, then implement to pass)

```bash
docker compose exec php php artisan test --filter=MediaControllerTest
docker compose exec php php artisan test --filter=CharacterResourcePortraitTest
```

**Commit:** `test(#120): Add tests for MediaController and CharacterResource portrait`

---

## Phase 9: Quality Gates

### Task 9.1: Run Pint
```bash
docker compose exec php ./vendor/bin/pint
```

### Task 9.2: Run full test suite
```bash
docker compose exec php php artisan test --testsuite=Feature-DB
```

### Task 9.3: Verify no regressions
```bash
docker compose exec php php artisan test --testsuite=Unit-DB
```

**Commit:** `style(#120): Apply Pint formatting`

---

## Phase 10: Documentation & Cleanup

### Task 10.1: Update CHANGELOG.md

Add under `[Unreleased]`:
```markdown
### Added
- Character portrait support with Spatie Laravel-Medialibrary (#120)
- Polymorphic MediaController for reusable media uploads
- Portrait URL fallback for external images
- Automatic thumbnail generation (150x150, 300x300)
```

### Task 10.2: Update CharacterController eager loading

**File:** `app/Http/Controllers/Api/CharacterController.php`

Add `'media'` to eager loading in `index()`, `show()`, `store()`, `update()` methods:
```php
$character->load([
    'race',
    'background',
    'characterClasses.characterClass.levelProgression',
    'characterClasses.subclass',
    'media',  // Add this line
]);
```

**Commit:** `docs(#120): Update CHANGELOG and add media eager loading`

---

## Phase 11: Final Verification

### Task 11.1: Run all relevant test suites
```bash
docker compose exec php php artisan test --testsuite=Unit-DB
docker compose exec php php artisan test --testsuite=Feature-DB
```

### Task 11.2: Manual smoke test
```bash
# Upload portrait
curl -X POST http://localhost:8080/api/v1/characters/1/media/portrait \
  -F "file=@/path/to/test-image.jpg"

# Get character (should include portrait)
curl http://localhost:8080/api/v1/characters/1 | jq '.data.portrait'

# Delete portrait
curl -X DELETE http://localhost:8080/api/v1/characters/1/media/portrait
```

### Task 11.3: Push and create PR
```bash
git push -u origin feature/issue-120-character-portrait
gh pr create --title "feat(#120): Add character portrait support" --body "$(cat <<'EOF'
## Summary
- Adds character portrait support using Spatie Laravel-Medialibrary
- Polymorphic MediaController for reusable media uploads across entities
- Portrait URL fallback for external images
- Automatic thumbnail generation (thumb: 150x150, medium: 300x300)

## Changes
- Install spatie/laravel-medialibrary package
- Add config/media.php for model registry
- Add HasMedia to Character model with portrait/token collections
- Add portrait_url column to characters table
- Create MediaController, MediaUploadRequest, MediaResource
- Update CharacterResource with portrait data
- Add polymorphic media routes

## Test Plan
- [x] Upload portrait to character
- [x] List portrait media
- [x] Delete portrait
- [x] External URL fallback
- [x] Validation (size, type, collection)
- [x] Single-file collection replacement

Closes #120

🤖 Generated with [Claude Code](https://claude.com/claude-code)
EOF
)"
```

---

## Rollback Plan

If issues arise:
1. Remove media routes from `routes/api.php`
2. Run `php artisan migrate:rollback` (removes portrait_url column)
3. Remove `HasMedia` interface from Character model
4. `composer remove spatie/laravel-medialibrary`
5. Delete `config/media.php`

---

## Summary

| Phase | Tasks | Estimated Commits |
|-------|-------|-------------------|
| 1. Scaffolding | 5 | 1 |
| 2. Configuration | 1 | 1 |
| 3. Data Model | 3 | 1 |
| 4. Form Requests | 2 | 1 |
| 5. API Resources | 2 | 1 |
| 6. Controller | 1 | 1 |
| 7. Routes | 1 | 1 |
| 8. Tests | 3 | 1 |
| 9. Quality Gates | 3 | 1 |
| 10. Documentation | 2 | 1 |
| 11. Final Verification | 3 | 0 |

**Total: 26 tasks, ~10 commits**
