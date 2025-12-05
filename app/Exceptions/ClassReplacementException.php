<?php

namespace App\Exceptions;

use Exception;

class ClassReplacementException extends Exception
{
    public function __construct(
        string $message,
        public readonly int $statusCode = 422,
    ) {
        parent::__construct($message);
    }

    public static function classNotFoundOnCharacter(string $className): self
    {
        return new self('Class not found on character', 404);
    }

    public static function levelTooHigh(int $level): self
    {
        return new self('Can only replace class at level 1');
    }

    public static function multipleClasses(): self
    {
        return new self('Cannot replace class when character has multiple classes');
    }

    public static function sameClass(): self
    {
        return new self('Cannot replace class with the same class');
    }

    public static function targetIsSubclass(string $className): self
    {
        return new self('Cannot use a subclass as the replacement class');
    }
}
