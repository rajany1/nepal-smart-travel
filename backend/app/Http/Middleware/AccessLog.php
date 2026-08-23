<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class AccessLog
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        Log::info('access', [
            'method' => $request->method(),
            'path' => $request->path(),
            'status' => $response->getStatusCode(),
            'ua' => substr((string) $request->userAgent(), 0, 120),
        ]);

        return $response;
    }
}