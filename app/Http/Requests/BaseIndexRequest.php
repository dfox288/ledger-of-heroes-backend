<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

abstract class BaseIndexRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Public API
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return array_merge([
            // Pagination
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],

            // Sorting
            'sort_by' => ['sometimes', Rule::in($this->getSortableColumns())],
            'sort_direction' => ['sometimes', Rule::in(['asc', 'desc'])],
        ], $this->entityRules());
    }

    /**
     * Entity-specific validation rules.
     */
    abstract protected function entityRules(): array;

    /**
     * Sortable columns for this entity.
     */
    abstract protected function getSortableColumns(): array;

    /**
     * Get cached lookup values from a model.
     *
     * @param  string  $key  Cache key suffix
     * @param  string  $model  Model class name
     * @param  string  $column  Column to pluck
     * @return array Lowercase values
     */
    protected function getCachedLookup(string $key, string $model, string $column = 'name'): array
    {
        return Cache::tags(['request_validation'])->remember(
            "request_validation.{$key}",
            now()->addDay(),
            fn () => app($model)::pluck($column)->map('strtolower')->toArray()
        );
    }
}
