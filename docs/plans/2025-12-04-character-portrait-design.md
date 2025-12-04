# Character Portrait/Image Design

**Issue:** #120
**Date:** 2025-12-04
**Status:** Approved

## Summary

Add character portrait support using Spatie Laravel-Medialibrary with a polymorphic MediaController that can be reused for other entities (notes, monsters, etc.) in the future.

## Architecture Decision

**Approach:** Generic Media Controller (Polymorphic)

A single `MediaController` handles media uploads for any model that implements `HasMedia`. This avoids creating separate controllers for each entity type while maintaining clean separation of concerns.

## Components

### 1. Package Installation

```bash
composer require spatie/laravel-medialibrary
php artisan vendor:publish --provider="Spatie\MediaLibrary\MediaLibraryServiceProvider" --tag="medialibrary-migrations"
php artisan migrate
```

### 2. Configuration

**config/media.php** - Registry of models that support media:

```php
<?php

return [
    'models' => [
        'characters' => [
            'class' => \App\Models\Character::class,
            'collections' => ['portrait', 'token'],
        ],
        // Future entities can be added here
        // 'notes' => [
        //     'class' => \App\Models\CharacterNote::class,
        //     'collections' => ['attachments'],
        // ],
    ],

    'max_file_size' => 2048, // 2MB in KB

    'accepted_mimetypes' => [
        'image/jpeg',
        'image/png',
        'image/webp',
    ],
];
```

### 3. Character Model Updates

Add `HasMedia` interface and configure collections:

```php
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Character extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $fillable = [
        // ... existing fields ...
        'portrait_url', // External URL fallback
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('portrait')
            ->singleFile()
            ->acceptsMimeTypes(config('media.accepted_mimetypes'));

        $this->addMediaCollection('token')
            ->singleFile()
            ->acceptsMimeTypes(config('media.accepted_mimetypes'));
    }

    public function registerMediaConversions(Media $media = null): void
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
}
```

### 4. Migration

**add_portrait_url_to_characters_table.php:**

```php
Schema::table('characters', function (Blueprint $table) {
    $table->string('portrait_url')->nullable()->after('asi_choices_remaining');
});
```

### 5. MediaController

**app/Http/Controllers/Api/MediaController.php:**

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Media\MediaUploadRequest;
use App\Http\Resources\MediaResource;
use Illuminate\Http\Response;
use Spatie\MediaLibrary\HasMedia;

class MediaController extends Controller
{
    /**
     * Upload media to a collection
     *
     * @param string $modelType Model type from config (e.g., 'characters')
     * @param int $modelId Model ID
     * @param string $collection Collection name (e.g., 'portrait')
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
     * List media in a collection
     */
    public function index(string $modelType, int $modelId, string $collection)
    {
        $model = $this->resolveModel($modelType, $modelId);
        $this->validateCollection($modelType, $collection);

        return MediaResource::collection($model->getMedia($collection));
    }

    /**
     * Delete media from a collection
     *
     * @param int|null $mediaId If null, clears entire collection
     */
    public function destroy(string $modelType, int $modelId, string $collection, ?int $mediaId = null)
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
     * Resolve model from type and ID
     */
    private function resolveModel(string $type, int $id): HasMedia
    {
        $config = config("media.models.{$type}");

        abort_unless($config, 404, "Unknown model type: {$type}");

        return $config['class']::findOrFail($id);
    }

    /**
     * Validate collection is allowed for model type
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

### 6. Form Request

**app/Http/Requests/Media/MediaUploadRequest.php:**

```php
<?php

namespace App\Http\Requests\Media;

use Illuminate\Foundation\Http\FormRequest;

class MediaUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Add authorization logic as needed
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

### 7. API Resource

**app/Http/Resources/MediaResource.php:**

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

### 8. CharacterResource Update

Add portrait data to the existing CharacterResource:

```php
// In toArray() method, add:
'portrait' => $this->getPortraitData(),

// Add private method:
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

### 9. Routes

Add to **routes/api.php**:

```php
use App\Http\Controllers\Api\MediaController;

// Polymorphic Media Routes
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

### 10. CharacterUpdateRequest

Add `portrait_url` validation:

```php
'portrait_url' => ['nullable', 'url', 'max:2048'],
```

## API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/v1/characters/{id}/media/portrait` | Upload portrait image |
| GET | `/api/v1/characters/{id}/media/portrait` | Get portrait media |
| DELETE | `/api/v1/characters/{id}/media/portrait` | Remove portrait |
| PATCH | `/api/v1/characters/{id}` | Set `portrait_url` (external) |

## Response Examples

**Uploaded portrait:**
```json
{
  "portrait": {
    "original": "https://example.com/storage/1/portrait.jpg",
    "thumb": "https://example.com/storage/1/conversions/portrait-thumb.jpg",
    "medium": "https://example.com/storage/1/conversions/portrait-medium.jpg",
    "is_uploaded": true
  }
}
```

**External URL:**
```json
{
  "portrait": {
    "original": "https://external-site.com/my-character.jpg",
    "thumb": null,
    "medium": null,
    "is_uploaded": false
  }
}
```

## Storage Configuration

**Development:** Use `public` disk (local storage with symbolic link)

**Production:** Configure S3/R2 in `config/media-library.php`:
```php
'disk_name' => env('MEDIA_DISK', 'public'),
```

## Future Extensions

- Add `token` collection conversions (circular crop for VTT)
- Add media to CharacterNote for image attachments
- Add media to Monster for custom artwork
- AI portrait generation integration

## Testing Requirements

- Unit tests for MediaController CRUD operations
- Feature tests for upload validation (size, type)
- Feature tests for collection access control
- Feature tests for CharacterResource portrait data
- Test external URL fallback behavior
