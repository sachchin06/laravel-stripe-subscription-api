<?php

namespace App\Http\Controllers\Api;

use App\Actions\Auth\AuthenticateUserAction;
use App\Actions\Auth\RegisterUserAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private readonly RegisterUserAction $registerUser,
        private readonly AuthenticateUserAction $authenticateUser
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $user = $this->registerUser->execute(
            name: $request->validated('name'),
            email: $request->validated('email'),
            password: $request->validated('password')
        );

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'user' => new UserResource($user),
            'token' => $token,
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = $this->authenticateUser->execute(
            email: $request->validated('email'),
            password: $request->validated('password')
        );

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'user' => new UserResource($user),
            'token' => $token,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully',
        ]);
    }
}
