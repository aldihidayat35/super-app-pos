<?php

namespace App\Contracts;

interface StatusContract
{
    public function label(): string;

    public function isFinal(): bool;
}
