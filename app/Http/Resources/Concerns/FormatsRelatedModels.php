<?php

namespace App\Http\Resources\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Provides standardized formatting for related models in API Resources.
 *
 * Common patterns:
 * - formatEntity($model, ['id', 'name', 'slug']) → ['id' => 1, 'name' => 'Test', 'slug' => 'test']
 * - formatEntityWith($model, ['id', 'name'], ['category' => fn($m) => $m->category]) → merges static + computed
 */
trait FormatsRelatedModels
{
    /**
     * Format an entity with specified fields.
     *
     * @param  Model|null  $entity  The model to format
     * @param  array<string>  $fields  Field names to extract
     * @return array<string, mixed>|null
     */
    protected function formatEntity(?Model $entity, array $fields = ['id', 'name', 'slug']): ?array
    {
        if (! $entity) {
            return null;
        }

        $result = [];
        foreach ($fields as $field) {
            $result[$field] = $entity->{$field};
        }

        return $result;
    }

    /**
     * Format an entity with both static fields and computed values.
     *
     * @param  Model|null  $entity  The model to format
     * @param  array<string>  $fields  Field names to extract directly
     * @param  array<string, callable>  $computed  Field name => callable($entity) for computed values
     * @return array<string, mixed>|null
     */
    protected function formatEntityWith(?Model $entity, array $fields, array $computed): ?array
    {
        if (! $entity) {
            return null;
        }

        $result = $this->formatEntity($entity, $fields) ?? [];

        foreach ($computed as $key => $callback) {
            $result[$key] = $callback($entity);
        }

        return $result;
    }

    /**
     * Format an entity with the standard [id, name, slug] pattern plus additional fields.
     *
     * @param  Model|null  $entity  The model to format
     * @param  array<string>  $extraFields  Additional field names to extract
     * @return array<string, mixed>|null
     */
    protected function formatEntityWithExtra(?Model $entity, array $extraFields): ?array
    {
        if (! $entity) {
            return null;
        }

        return $this->formatEntity($entity, array_merge(['id', 'name', 'slug'], $extraFields));
    }
}
