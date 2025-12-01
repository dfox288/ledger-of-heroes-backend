<?php

namespace App\Events;

use App\Models\Character;
use Illuminate\Foundation\Events\Dispatchable;

class CharacterUpdated
{
    use Dispatchable;

    public function __construct(
        public readonly Character $character
    ) {}
}
