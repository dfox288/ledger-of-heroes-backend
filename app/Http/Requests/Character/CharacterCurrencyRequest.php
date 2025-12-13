<?php

declare(strict_types=1);

namespace App\Http\Requests\Character;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class CharacterCurrencyRequest extends FormRequest
{
    private const CURRENCY_TYPES = ['pp', 'gp', 'ep', 'sp', 'cp'];

    private const MAX_CURRENCY_VALUE = 999999;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $regex = 'regex:/^[+-]?\d+$/';
        $max = 'max_digits:6'; // 999999

        return [
            'pp' => ['sometimes', 'string', $regex],
            'gp' => ['sometimes', 'string', $regex],
            'ep' => ['sometimes', 'string', $regex],
            'sp' => ['sometimes', 'string', $regex],
            'cp' => ['sometimes', 'string', $regex],
        ];
    }

    public function messages(): array
    {
        return [
            'pp.regex' => 'Platinum must be a number optionally prefixed with + or - (e.g., "-5", "+10", "25").',
            'gp.regex' => 'Gold must be a number optionally prefixed with + or - (e.g., "-5", "+10", "25").',
            'ep.regex' => 'Electrum must be a number optionally prefixed with + or - (e.g., "-5", "+10", "25").',
            'sp.regex' => 'Silver must be a number optionally prefixed with + or - (e.g., "-5", "+10", "25").',
            'cp.regex' => 'Copper must be a number optionally prefixed with + or - (e.g., "-5", "+10", "25").',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            // Check that at least one currency field is provided
            $hasCurrency = false;
            foreach (self::CURRENCY_TYPES as $type) {
                if ($this->has($type)) {
                    $hasCurrency = true;
                    break;
                }
            }

            if (! $hasCurrency) {
                $validator->errors()->add('currency', 'At least one currency type must be provided.');
            }

            // Validate max values for absolute sets
            foreach (self::CURRENCY_TYPES as $type) {
                if ($this->has($type)) {
                    $value = (string) $this->input($type);
                    $numericValue = abs((int) preg_replace('/^[+-]/', '', $value));
                    if ($numericValue > self::MAX_CURRENCY_VALUE) {
                        $validator->errors()->add($type, 'Currency value cannot exceed '.self::MAX_CURRENCY_VALUE.'.');
                    }
                }
            }

            // Check for unknown fields
            $allowedFields = array_merge(self::CURRENCY_TYPES, ['_token', '_method']);
            foreach ($this->all() as $key => $value) {
                if (! in_array($key, $allowedFields, true)) {
                    $validator->errors()->add($key, "Unknown currency type: {$key}");
                }
            }
        });
    }

    /**
     * Parse currency changes into operation arrays.
     *
     * @return array<string, array{type: string, value: int}>
     */
    public function parseCurrencyChanges(): array
    {
        $changes = [];

        foreach (self::CURRENCY_TYPES as $type) {
            if (! $this->has($type)) {
                continue;
            }

            $value = (string) $this->input($type);
            $changes[$type] = $this->parseValue($value);
        }

        return $changes;
    }

    /**
     * Parse a single currency value into operation type and amount.
     *
     * @return array{type: string, value: int}
     */
    private function parseValue(string $value): array
    {
        if (str_starts_with($value, '-')) {
            return [
                'type' => 'subtract',
                'value' => abs((int) $value),
            ];
        }

        if (str_starts_with($value, '+')) {
            return [
                'type' => 'add',
                'value' => (int) ltrim($value, '+'),
            ];
        }

        return [
            'type' => 'set',
            'value' => (int) $value,
        ];
    }
}
