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
     *
     * @response array{data: array<int, array{id: int, collection: string, file_name: string, mime_type: string, size: int, urls: array{original: string, thumb: string|null, medium: string|null}, created_at: string|null}>}
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
