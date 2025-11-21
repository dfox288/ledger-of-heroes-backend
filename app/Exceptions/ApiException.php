<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;

abstract class ApiException extends \Exception
{
    /**
     * Render the exception as an HTTP response.
     */
    abstract public function render($request): JsonResponse;
}
