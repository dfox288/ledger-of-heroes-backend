<?php

namespace App\Exceptions;

use App\Enums\SpellSlotType;
use Exception;

class InsufficientSpellSlotsException extends Exception
{
    public function __construct(
        public readonly int $spellLevel,
        public readonly SpellSlotType $slotType,
        public readonly int $available,
        ?string $message = null
    ) {
        $typeLabel = $slotType->label();
        $message = $message ?? ($available === 0
            ? "No {$typeLabel} slots available at level {$spellLevel}."
            : "Not enough {$typeLabel} slots at level {$spellLevel}. Have {$available}, need 1.");

        parent::__construct($message);
    }
}
