<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasActiveSubscription
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()->hasActiveSubscription()) {
            return response()->json([
                'message' => 'Active subscription required',
            ], 403);
        }

        return $next($request);
    }
}
