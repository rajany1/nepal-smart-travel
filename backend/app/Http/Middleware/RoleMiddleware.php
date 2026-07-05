<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (!auth()->check()) {
            abort(403, 'Unauthorized');
        }

        if (!in_array(auth()->user()->role?->name, $roles, true)) {
            abort(403, 'Unauthorized');
        }

        return $next($request);
    }
}
