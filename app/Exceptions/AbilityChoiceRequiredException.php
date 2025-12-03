<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\Feat;
use Exception;
use Illuminate\Http\JsonResponse;

class AbilityChoiceRequiredException extends Exception
{
    /**
     * @param  array<string>  $allowedAbilities
     */
    public function __construct(
        public readonly Feat $feat,
        public readonly array $allowedAbilities,
        string $message = 'This feat requires choosing an ability score to increase.',
    ) {
        parent::__construct($message);
    }

    /**
     * Render the exception as an HTTP response.
     */
    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'feat_id' => $this->feat->id,
            'feat_name' => $this->feat->name,
            'allowed_abilities' => $this->allowedAbilities,
        ], 422);
    }
}
