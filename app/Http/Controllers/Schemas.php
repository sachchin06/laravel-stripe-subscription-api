<?php

namespace App\Http\Controllers;

/**
 * @OA\Schema(
 *     schema="User",
 *     type="object",
 *     title="User",
 *     description="User model",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="John Doe"),
 *     @OA\Property(property="email", type="string", format="email", example="john@example.com"),
 *     @OA\Property(property="email_verified_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="created_at", type="string", format="date-time")
 * )
 * 
 * @OA\Schema(
 *     schema="Plan",
 *     type="object",
 *     title="Plan",
 *     description="Subscription plan model",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="Pro Plan"),
 *     @OA\Property(property="slug", type="string", example="pro"),
 *     @OA\Property(property="description", type="string", example="Professional plan with all features"),
 *     @OA\Property(property="stripe_product_id", type="string", example="prod_1234567890"),
 *     @OA\Property(
 *         property="prices",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/PlanPrice")
 *     ),
 *     @OA\Property(property="created_at", type="string", format="date-time")
 * )
 * 
 * @OA\Schema(
 *     schema="PlanPrice",
 *     type="object",
 *     title="Plan Price",
 *     description="Plan pricing model",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="plan_id", type="integer", example=1),
 *     @OA\Property(property="stripe_price_id", type="string", example="price_1234567890"),
 *     @OA\Property(property="price", type="number", format="float", example=29.99),
 *     @OA\Property(property="currency", type="string", example="usd"),
 *     @OA\Property(property="interval", type="string", enum={"month", "year"}, example="month"),
 *     @OA\Property(property="created_at", type="string", format="date-time")
 * )
 * 
 * @OA\Schema(
 *     schema="Subscription",
 *     type="object",
 *     title="Subscription",
 *     description="User subscription model",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="user_id", type="integer", example=1),
 *     @OA\Property(property="plan", ref="#/components/schemas/Plan"),
 *     @OA\Property(property="price", ref="#/components/schemas/PlanPrice"),
 *     @OA\Property(property="stripe_subscription_id", type="string", example="sub_1234567890"),
 *     @OA\Property(property="stripe_customer_id", type="string", example="cus_1234567890"),
 *     @OA\Property(property="status", type="string", enum={"active", "trialing", "past_due", "canceled", "unpaid", "incomplete"}, example="active"),
 *     @OA\Property(property="trial_ends_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="ends_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class Schemas
{
    // This class only exists to hold schema definitions
}
