<?php

namespace App\Exceptions;

use App\Models\Item;
use Exception;
use Illuminate\Http\JsonResponse;

class ItemNotEquippableException extends Exception
{
    public function __construct(
        public readonly Item $item,
        ?string $message = null
    ) {
        $message ??= "Item '{$item->name}' cannot be equipped. Only armor, shields, and weapons can be equipped.";
        parent::__construct($message);
    }

    /**
     * Render the exception as an HTTP response.
     */
    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'item' => [
                'id' => $this->item->id,
                'name' => $this->item->name,
                'type' => $this->item->itemType?->name,
            ],
        ], 422);
    }
}
