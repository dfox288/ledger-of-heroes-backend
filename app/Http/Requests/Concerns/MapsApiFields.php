<?php

namespace App\Http\Requests\Concerns;

/**
 * Reusable trait for mapping API field names to database column names.
 *
 * Use this trait to standardize field mapping across form requests.
 * API consumers use user-friendly names (e.g., 'class'), which are mapped
 * to internal database column names (e.g., 'class_slug').
 *
 * Usage:
 * ```php
 * class MyFormRequest extends FormRequest
 * {
 *     use MapsApiFields;
 *
 *     protected array $fieldMappings = [
 *         'class' => 'class_slug',
 *         'race' => 'race_slug',
 *     ];
 *
 *     protected function prepareForValidation(): void
 *     {
 *         $this->mapApiFields();
 *     }
 * }
 * ```
 */
trait MapsApiFields
{
    /**
     * Map API field names to database column names.
     *
     * Call this method from prepareForValidation() to apply mappings.
     * Requires $fieldMappings property to be defined in the using class.
     *
     * Note: Does not overwrite if the database field is already present
     * (allows backwards-compatible APIs where clients may send either field).
     */
    protected function mapApiFields(): void
    {
        foreach ($this->fieldMappings as $apiField => $dbColumn) {
            if ($this->has($apiField) && ! $this->has($dbColumn)) {
                $this->merge([$dbColumn => $this->input($apiField)]);
            }
        }
    }
}
