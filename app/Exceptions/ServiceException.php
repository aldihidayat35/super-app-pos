<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class ServiceException extends RuntimeException
{
    /** @param array<string, mixed> $context */
    public function __construct(
        string $message,
        public readonly array $context = [],
        public readonly int $httpStatus = 422,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    /** @param array<string, mixed> $context */
    public static function validation(string $message, array $context = []): self
    {
        return new self($message, $context, 422);
    }
}
