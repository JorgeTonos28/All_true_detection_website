<?php

namespace App\Services;

use RuntimeException;

final class DiditRequestFailure extends RuntimeException
{
    public function __construct(
        public readonly ?int $httpStatus,
        public readonly string $reason,
        public readonly bool $creationRejected = false,
    ) {
        parent::__construct('No se pudo confirmar la operación con el proveedor de identidad.');
    }
}
