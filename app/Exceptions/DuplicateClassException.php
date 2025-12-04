<?php

namespace App\Exceptions;

use Exception;

class DuplicateClassException extends Exception
{
    public function __construct(
        public readonly string $className,
        public readonly ?int $characterId = null,
        public readonly ?string $characterName = null,
    ) {
        $charInfo = $characterId !== null
            ? "Character '{$characterName}' (ID: {$characterId})"
            : 'Character';

        parent::__construct("{$charInfo} already has levels in {$className}.");
    }
}
