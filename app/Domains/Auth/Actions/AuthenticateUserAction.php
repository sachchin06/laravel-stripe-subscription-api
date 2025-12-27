<?php

namespace App\Domains\Auth\Actions;

use App\Domains\Auth\DTOs\LoginData;
use App\Domains\Auth\Services\AuthService;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Action for authenticating a user
 */
class AuthenticateUserAction
{
    public function __construct(
        private readonly AuthService $authService
    ) {}

    /**
     * Execute user authentication
     * 
     * @throws ValidationException
     */
    public function execute(LoginData $data): User
    {
        $user = $this->authService->getUserByEmail($data->email);

        if (!$user || !$this->authService->verifyPassword($user, $data->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        return $user;
    }
}