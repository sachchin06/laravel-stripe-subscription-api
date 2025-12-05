<?php

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class RegisterUserAction
{
    public function execute(string $name, string $email, string $password): User
    {
        return User::create([
            'name' => $name,
            'email' => strtolower($email),
            'password' => Hash::make($password),
        ]);
    }
}
