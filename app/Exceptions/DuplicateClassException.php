<?php

namespace App\Exceptions;

use Exception;

class DuplicateClassException extends Exception
{
    public function __construct(string $className)
    {
        parent::__construct("Character already has levels in {$className}");
    }
}
