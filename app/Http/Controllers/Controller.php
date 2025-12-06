<?php

namespace App\Http\Controllers;

/**
 * @OA\Info(
 *     title="Laravel Stripe Subscription API",
 *     version="1.0.0",
 *     description="API for managing subscription-based services with Stripe integration",
 *     @OA\Contact(
 *         email="support@example.com"
 *     ),
 *     @OA\License(
 *         name="MIT",
 *         url="https://opensource.org/licenses/MIT"
 *     )
 * )
 * 
 * @OA\Server(
 *     url="http://127.0.0.1:8000",
 *     description="Local Development Server"
 * )
 * 
 * @OA\Server(
 *     url="https://api.yourdomain.com",
 *     description="Production Server"
 * )
 * 
 * @OA\SecurityScheme(
 *     securityScheme="sanctum",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT",
 *     description="Enter your Bearer token in the format: Bearer {token}"
 * )
 * 
 * @OA\Tag(
 *     name="Authentication",
 *     description="User authentication endpoints"
 * )
 * 
 * @OA\Tag(
 *     name="Plans",
 *     description="Subscription plans management"
 * )
 * 
 * @OA\Tag(
 *     name="Subscriptions",
 *     description="User subscription management"
 * )
 * 
 * @OA\Tag(
 *     name="Webhooks",
 *     description="Stripe webhook endpoints"
 * )
 */
abstract class Controller
{
    //
}
