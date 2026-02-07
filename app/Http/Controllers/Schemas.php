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
 * 
 * @OA\Schema(
 *     schema="Invoice",
 *     type="object",
 *     title="Invoice",
 *     description="Stripe invoice model",
 *     @OA\Property(property="id", type="string", example="in_1234567890"),
 *     @OA\Property(property="number", type="string", example="INV-2024-001"),
 *     @OA\Property(property="amount_paid", type="integer", example=2999, description="Amount paid in cents"),
 *     @OA\Property(property="amount_due", type="integer", example=0, description="Amount due in cents"),
 *     @OA\Property(property="currency", type="string", example="USD"),
 *     @OA\Property(property="status", type="string", enum={"draft", "open", "paid", "void", "uncollectible"}, example="paid"),
 *     @OA\Property(property="created", type="integer", example=1640995200, description="Unix timestamp"),
 *     @OA\Property(property="due_date", type="integer", example=1640995200, description="Unix timestamp"),
 *     @OA\Property(property="hosted_invoice_url", type="string", format="url", example="https://invoice.stripe.com/i/..."),
 *     @OA\Property(property="invoice_pdf", type="string", format="url", example="https://pay.stripe.com/invoice/.../pdf"),
 *     @OA\Property(property="description", type="string", example="Pro Plan - Monthly")
 * )
 * 
 * @OA\Schema(
 *     schema="UpcomingInvoice",
 *     type="object",
 *     title="Upcoming Invoice",
 *     description="Upcoming invoice preview",
 *     @OA\Property(property="amount_due", type="integer", example=2999, description="Amount due in cents"),
 *     @OA\Property(property="currency", type="string", example="USD"),
 *     @OA\Property(property="period_start", type="integer", example=1640995200, description="Unix timestamp"),
 *     @OA\Property(property="period_end", type="integer", example=1643673600, description="Unix timestamp"),
 *     @OA\Property(property="next_payment_attempt", type="integer", example=1643673600, description="Unix timestamp"),
 *     @OA\Property(property="description", type="string", example="Pro Plan - Monthly")
 * )
 * 
 * @OA\Schema(
 *     schema="UsageSummary",
 *     type="object",
 *     title="Usage Summary",
 *     description="User feature usage summary",
 *     @OA\Property(
 *         property="api_calls",
 *         type="object",
 *         @OA\Property(property="current", type="integer", example=1250, description="Current usage this month"),
 *         @OA\Property(property="limit", type="integer", example=10000, description="Usage limit for current plan"),
 *         @OA\Property(property="percentage", type="number", format="float", example=12.5, description="Percentage of limit used"),
 *         @OA\Property(property="unlimited", type="boolean", example=false, description="Whether usage is unlimited")
 *     ),
 *     @OA\Property(
 *         property="storage_mb",
 *         type="object",
 *         @OA\Property(property="current", type="integer", example=450),
 *         @OA\Property(property="limit", type="integer", example=1000),
 *         @OA\Property(property="percentage", type="number", format="float", example=45.0),
 *         @OA\Property(property="unlimited", type="boolean", example=false)
 *     ),
 *     @OA\Property(
 *         property="team_members",
 *         type="object",
 *         @OA\Property(property="current", type="integer", example=3),
 *         @OA\Property(property="limit", type="integer", example=5),
 *         @OA\Property(property="percentage", type="number", format="float", example=60.0),
 *         @OA\Property(property="unlimited", type="boolean", example=false)
 *     )
 * )
 */
class Schemas
{
    // This class only exists to hold schema definitions
}
