<?php

namespace App\Exceptions;

use Exception;

class MulticlassPrerequisiteException extends Exception
{
    public function __construct(
        public readonly array $errors,
        string $message = 'Multiclass prerequisites not met'
    ) {
        parent::__construct($message);
    }
}
