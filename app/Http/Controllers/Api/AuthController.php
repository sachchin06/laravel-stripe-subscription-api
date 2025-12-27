<?php

namespace App\Http\Controllers\Api;

use App\Domains\Auth\Actions\AuthenticateUserAction;
use App\Domains\Auth\Actions\LogoutUserAction;
use App\Domains\Auth\Actions\RegisterUserAction;
use App\Domains\Auth\DTOs\LoginData;
use App\Domains\Auth\DTOs\RegisterUserData;
use App\Domains\Auth\Services\AuthService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller for authentication endpoints
 * 
 * This controller provides a thin HTTP layer for authentication operations,
 * delegating business logic to domain actions and services.
 */
class AuthController extends Controller
{
    public function __construct(
        private readonly RegisterUserAction $registerUser,
        private readonly AuthenticateUserAction $authenticateUser,
        private readonly LogoutUserAction $logoutUser,
        private readonly AuthService $authService
    ) {}

    /**
     * @OA\Post(
     *     path="/api/auth/register",
     *     summary="Register a new user",
     *     tags={"Authentication"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name","email","password","password_confirmation"},
     *             @OA\Property(property="name", type="string", example="John Doe"),
     *             @OA\Property(property="email", type="string", format="email", example="john@example.com"),
     *             @OA\Property(property="password", type="string", format="password", example="password123"),
     *             @OA\Property(property="password_confirmation", type="string", format="password", example="password123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="User registered successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="user", ref="#/components/schemas/User"),
     *             @OA\Property(property="token", type="string", example="1|abcdef123456...")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The email has already been taken."),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $registerData = new RegisterUserData(
            name: $request->validated('name'),
            email: $request->validated('email'),
            password: $request->validated('password')
        );

        $user = $this->registerUser->execute($registerData);
        $token = $this->authService->createToken($user);

        return response()->json([
            'user' => new UserResource($user),
            'token' => $token,
        ], 201);
    }

    /**
     * @OA\Post(
     *     path="/api/auth/login",
     *     summary="Login user",
     *     tags={"Authentication"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email","password"},
     *             @OA\Property(property="email", type="string", format="email", example="john@example.com"),
     *             @OA\Property(property="password", type="string", format="password", example="password123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Login successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="user", ref="#/components/schemas/User"),
     *             @OA\Property(property="token", type="string", example="2|abcdef123456...")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Invalid credentials",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The provided credentials are incorrect."),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $loginData = new LoginData(
            email: $request->validated('email'),
            password: $request->validated('password')
        );

        $user = $this->authenticateUser->execute($loginData);
        $token = $this->authService->createToken($user);

        return response()->json([
            'user' => new UserResource($user),
            'token' => $token,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/auth/logout",
     *     summary="Logout user",
     *     tags={"Authentication"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Logout successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Logged out successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     )
     * )
     */
    public function logout(Request $request): JsonResponse
    {
        $this->logoutUser->execute($request->user());

        return response()->json([
            'message' => 'Logged out successfully',
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/auth/user",
     *     summary="Get authenticated user",
     *     tags={"Authentication"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="User details",
     *         @OA\JsonContent(ref="#/components/schemas/User")
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */
    public function user(Request $request): UserResource
    {
        return new UserResource($request->user());
    }
}
