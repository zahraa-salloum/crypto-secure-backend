<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * AdminMiddleware
 * Allows only users with user_type_id = 1 (admin) to access the route.
 */
class AdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden. Admin access required.',
                'errors'  => ['You do not have permission to access this resource.'],
            ], 403);
        }

        return $next($request);
    }
}
