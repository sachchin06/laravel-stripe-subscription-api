<?php

namespace App\Domains\Auth\DTOs;

use App\Domains\Shared\DTOs\BaseDTO;

/**
 * Data Transfer Object for user login
 */
readonly class LoginData extends BaseDTO
{
    public function __construct(
        public string $email,
        public string $password
    ) {}

    /**
     * Convert to array for authentication
     */
    public function toArray(): array
    {
        return [
            'email' => $this->email,
            'password' => $this->password,
        ];
    }
}