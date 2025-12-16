<?php

namespace App\Exceptions;

use App\Models\Character;
use App\Models\Spell;
use Exception;
use Illuminate\Http\JsonResponse;

class SpellManagementException extends Exception
{
    public function __construct(
        string $message,
        public readonly ?string $field = null,
        public readonly int $statusCode = 422
    ) {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        $response = ['message' => $this->getMessage()];

        if ($this->field) {
            $response['errors'] = [$this->field => [$this->getMessage()]];
        }

        return response()->json($response, $this->statusCode);
    }

    public static function spellNotOnClassList(Spell $spell, Character $character): self
    {
        return new self(
            "The spell '{$spell->name}' is not available for this character's class.",
            'spell_id'
        );
    }

    public static function spellLevelTooHigh(Spell $spell, Character $character, int $maxLevel): self
    {
        return new self(
            "Cannot learn '{$spell->name}' (level {$spell->level}). Maximum spell level at character level {$character->level} is {$maxLevel}.",
            'spell_id'
        );
    }

    public static function spellAlreadyKnown(Spell $spell, Character $character): self
    {
        return new self(
            "The spell '{$spell->name}' is already known by this character.",
            'spell_id'
        );
    }

    public static function spellNotKnown(Spell $spell, Character $character): self
    {
        return new self(
            "The spell '{$spell->name}' is not known by this character.",
            null,
            404
        );
    }

    public static function cannotPrepareCantrip(Spell $spell): self
    {
        return new self(
            'Cantrips cannot be prepared - they are always ready.',
            null,
            422
        );
    }

    public static function cannotUnprepareAlwaysPrepared(Spell $spell): self
    {
        return new self(
            "The spell '{$spell->name}' is always prepared and cannot be unprepared.",
            null,
            422
        );
    }

    public static function preparationLimitReached(Character $character, int $limit): self
    {
        return new self(
            'Preparation limit reached. Unprepare a spell first.',
            null,
            422
        );
    }

    public static function cannotUnprepareCantrip(Spell $spell): self
    {
        return new self(
            'Cantrips cannot be unprepared - they are always ready.',
            null,
            422
        );
    }
}
