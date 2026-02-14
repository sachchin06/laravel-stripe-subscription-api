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

    /**
     * Convert to JSON
     */
    public function toJson(): string
    {
        return json_encode($this->toArray());
    }
}