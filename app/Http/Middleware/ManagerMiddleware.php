<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ManagerMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!auth()->check()) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $user = auth()->user();
        if (!$user->isManager() && !$user->isAdmin()) {
            return response()->json(['message' => 'Forbidden. Manager or Admin access required.'], 403);
        }

        return $next($request);
    }
}
