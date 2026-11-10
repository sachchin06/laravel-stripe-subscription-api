<?php

namespace App\Domains\Shared\DTOs;

/**
 * Base DTO class with common functionality
 */
abstract readonly class BaseDTO
{
    /**
     * Convert DTO to array
     */
    abstract public function toArray(): array;

    public function toJson(): string
    {
        return json_encode($this->toArray());
    }
}
