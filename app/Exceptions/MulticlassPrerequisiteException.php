<?php

namespace App\Exceptions;

use Exception;

class MulticlassPrerequisiteException extends Exception
{
    public function __construct(
        public readonly array $errors,
        public readonly ?int $characterId = null,
        public readonly ?string $characterName = null,
    ) {
        $charInfo = $characterId !== null
            ? "Character '{$characterName}' (ID: {$characterId})"
            : 'Character';

        parent::__construct("{$charInfo} does not meet multiclass prerequisites.");
    }
}
