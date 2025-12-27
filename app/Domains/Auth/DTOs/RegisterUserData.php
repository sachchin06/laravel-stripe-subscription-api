<?php

namespace App\Domains\Auth\DTOs;

use App\Domains\Shared\DTOs\BaseDTO;

/**
 * Data Transfer Object for user registration
 */
readonly class RegisterUserData extends BaseDTO
{
    public function __construct(
        public string $name,
        public string $email,
        public string $password
    ) {}

    /**
     * Convert to array for Eloquent operations
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
            'password' => $this->password,
        ];
    }
}