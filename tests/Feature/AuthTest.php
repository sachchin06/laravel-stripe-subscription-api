<?php

use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

describe('registration', function () {
    test('registration requires a name, an email and a confirmed password', function () {
        $this->postJson('/api/auth/register', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'password']);

        $this->postJson('/api/auth/register', [
            'name' => 'Ada', 'email' => 'not-an-email', 'password' => 'Password123!', 'password_confirmation' => 'different',
        ])->assertStatus(422)->assertJsonValidationErrors(['email', 'password']);
    });

    test('registration rejects an email that is already registered', function () {
        User::factory()->create(['email' => 'ada@example.com']);

        $this->postJson('/api/auth/register', [
            'name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'Password123!', 'password_confirmation' => 'Password123!',
        ])->assertStatus(422)->assertJsonValidationErrors('email');
    });

    test('registration returns the new user and an API token, and ignores is_admin', function () {
        $this->postJson('/api/auth/register', [
            'name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'Password123!',
            'password_confirmation' => 'Password123!', 'is_admin' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('user.email', 'ada@example.com')
            ->assertJsonStructure(['user' => ['id', 'name', 'email'], 'token']);

        expect(User::where('email', 'ada@example.com')->first()->is_admin)->toBeFalse();
    });
});

describe('login and logout', function () {
    test('login with the right password returns a working token', function () {
        $user = User::factory()->create(['email' => 'ada@example.com']);

        $token = $this->postJson('/api/auth/login', ['email' => 'Ada@Example.com', 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->json('token');

        $this->getJson('/api/auth/user', ['Authorization' => "Bearer {$token}"])
            ->assertOk()
            ->assertJsonPath('data.email', 'ada@example.com');
    });

    test('login with a wrong password fails with 422 and no token', function () {
        User::factory()->create(['email' => 'ada@example.com']);

        $this->postJson('/api/auth/login', ['email' => 'ada@example.com', 'password' => 'wrong'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email')
            ->assertJsonMissingPath('token');
    });

    test('login is rate limited to five attempts a minute per email and IP', function () {
        User::factory()->create(['email' => 'ada@example.com']);

        foreach (range(1, 5) as $attempt) {
            $this->postJson('/api/auth/login', ['email' => 'ada@example.com', 'password' => 'wrong'])->assertStatus(422);
        }

        $this->postJson('/api/auth/login', ['email' => 'ada@example.com', 'password' => 'password'])
            ->assertStatus(429);
    });

    test('logout revokes the token that was used', function () {
        $user = User::factory()->create();
        $token = $user->createToken('api-token')->plainTextToken;

        $this->postJson('/api/auth/logout', [], ['Authorization' => "Bearer {$token}"])->assertOk();

        expect(PersonalAccessToken::count())->toBe(0);

        $this->app['auth']->forgetGuards();
        $this->getJson('/api/auth/user', ['Authorization' => "Bearer {$token}"])->assertUnauthorized();
    });

    test('tokens stop working after the configured lifetime', function () {
        $token = User::factory()->create()->createToken('api-token')->plainTextToken;

        $this->travel(config('sanctum.expiration') + 1)->minutes();

        $this->getJson('/api/auth/user', ['Authorization' => "Bearer {$token}"])->assertUnauthorized();
    });
});
