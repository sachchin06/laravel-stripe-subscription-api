<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsSubscribedToPlan
{
    public function handle(Request $request, Closure $next, string $planSlug): Response
    {
        if (! $request->user()->isSubscribedTo($planSlug)) {
            return response()->json([
                'message' => "Subscription to '{$planSlug}' plan required",
            ], 403);
        }

        return $next($request);
    }
}
