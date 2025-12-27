<?php

namespace App\Domains\Auth\Actions;

use App\Models\User;

/**
 * Action for logging out a user
 */
class LogoutUserAction
{
    /**
     * Execute user logout
     * 
     * Revokes the current access token
     */
    public function execute(User $user): void
    {
        // Delete the current access token
        $user->currentAccessToken()?->delete();
    }
}