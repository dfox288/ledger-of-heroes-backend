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
     */
    protected function mapApiFields(): void
    {
        foreach ($this->fieldMappings as $apiField => $dbColumn) {
            if ($this->has($apiField)) {
                $this->merge([$dbColumn => $this->input($apiField)]);
            }
        }
    }
}
