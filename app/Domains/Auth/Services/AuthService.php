<?php

namespace App\Domains\Auth\Services;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/**
 * Service for authentication operations
 */
class AuthService
{
    /**
     * Attempt to authenticate user with email and password
     */
    public function attemptLogin(string $email, string $password): bool
    {
        return Auth::attempt([
            'email' => strtolower($email),
            'password' => $password,
        ]);
    }

    /**
     * Get user by email address
     */
    public function getUserByEmail(string $email): ?User
    {
        return User::where('email', strtolower($email))->first();
    }

    /**
     * Create API token for user
     */
    public function createToken(User $user, string $name = 'api-token'): string
    {
        return $user->createToken($name)->plainTextToken;
    }

    /**
     * Verify user password
     */
    public function verifyPassword(User $user, string $password): bool
    {
        return Hash::check($password, $user->password);
    }

    /**
     * Update user password
     */
    public function updatePassword(User $user, string $newPassword): void
    {
        $user->update([
            'password' => Hash::make($newPassword),
        ]);

        // Revoke all existing tokens for security
        $user->tokens()->delete();
    }

    /**
     * Check if email is already registered
     */
    public function emailExists(string $email): bool
    {
        return User::where('email', strtolower($email))->exists();
    }
}