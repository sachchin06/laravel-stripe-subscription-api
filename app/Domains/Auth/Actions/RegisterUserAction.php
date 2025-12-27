<?php

namespace App\Domains\Auth\Actions;

use App\Domains\Auth\DTOs\RegisterUserData;
use App\Domains\Auth\Events\UserRegistered;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * Action for registering a new user
 */
class RegisterUserAction
{
    /**
     * Execute user registration
     */
    public function execute(RegisterUserData $data): User
    {
        $user = User::create([
            'name' => $data->name,
            'email' => $data->email,
            'password' => Hash::make($data->password),
        ]);

        // Fire domain event
        event(new UserRegistered($user));

        return $user;
    }
}