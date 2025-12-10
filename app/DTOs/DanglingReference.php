<?php

declare(strict_types=1);

namespace App\DTOs;

use JsonSerializable;

/**
 * Data Transfer Object for a dangling reference.
 *
 * Represents a reference to an entity that doesn't exist in the database.
 */
class DanglingReference implements JsonSerializable
{
    public function __construct(
        public readonly string $reference,
        public readonly string $type,
        public readonly string $message,
    ) {}

    /**
     * Create a dangling reference with a human-readable message.
     */
    public static function create(string $reference, string $type): self
    {
        $typeName = ucfirst($type);
        $message = "{$typeName} \"{$reference}\" not found";

        return new self($reference, $type, $message);
    }

    /**
     * @return array{reference: string, type: string, message: string}
     */
    public function jsonSerialize(): array
    {
        return [
            'reference' => $this->reference,
            'type' => $this->type,
            'message' => $this->message,
        ];
    }
}
