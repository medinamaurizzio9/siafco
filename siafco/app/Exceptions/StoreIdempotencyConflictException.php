<?php

namespace App\Exceptions;

use RuntimeException;

class StoreIdempotencyConflictException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('La clave de idempotencia ya fue utilizada con otro contenido.', 409);
    }
}
