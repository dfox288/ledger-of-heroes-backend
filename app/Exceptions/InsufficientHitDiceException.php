<?php

namespace App\Exceptions;

use Exception;

class InsufficientHitDiceException extends Exception
{
    public function __construct(
        public readonly string $dieType,
        public readonly int $available,
        public readonly int $requested,
        ?string $message = null
    ) {
        $message = $message ?? ($available === 0
            ? "Character does not have any {$dieType} hit dice."
            : "Not enough {$dieType} hit dice available. Have {$available}, need {$requested}.");

        parent::__construct($message);
    }
}
