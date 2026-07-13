<?php

namespace App\Data;

final readonly class WorkLocationContext
{
    public function __construct(
        public ?string $type,
        public ?int $id,
    ) {}

    public function isSelected(): bool
    {
        return $this->type !== null && $this->id !== null;
    }
}
