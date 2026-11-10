<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Local development data. Both users have the password "password".
     */
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'test@example.com'],
            ['name' => 'Test User', 'password' => 'password']
        );

        $admin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            ['name' => 'Admin User', 'password' => 'password']
        );
        // is_admin is not mass assignable, so it is set explicitly.
        $admin->forceFill(['is_admin' => true])->save();

        $this->call(PlanPriceSeeder::class);
    }
}
