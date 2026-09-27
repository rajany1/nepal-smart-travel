<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class IdempotencyMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // Only apply to mutating requests
        if (!in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            return $next($request);
        }

        // Skip if already has idempotency key in headers
        $idempotencyKey = $request->header('Idempotency-Key')
            ?? $request->header('X-Idempotency-Key')
            ?? $request->input('_idempotency_key');

        // Generate one if not provided (for API clients that don't send it)
        if (!$idempotencyKey && $request->user()) {
            $idempotencyKey = 'auto-' . $request->user()->id . '-' . $request->route()->getName() . '-' . md5($request->getContent());
        }

        if (!$idempotencyKey) {
            return $next($request);
        }

        // Check if we've seen this key before
        $existing = \App\Models\IdempotencyKey::where('key', $idempotencyKey)->first();

        if ($existing) {
            // Return cached response
            if ($existing->response_body) {
                return response($existing->response_body, $existing->response_code)
                    ->withHeaders($existing->response_headers ?? [])
                    ->header('X-Idempotency-Replay', 'true');
            }

            // Still processing
            return response()->json([
                'success' => false,
                'error' => 'Request with this idempotency key is still being processed',
                'code' => 'IDEMPOTENCY_PROCESSING',
            ], 409);
        }

        // Store the key as "processing"
        \App\Models\IdempotencyKey::create([
            'key' => $idempotencyKey,
            'user_id' => $request->user()?->id,
            'route' => $request->route()->getName() ?? $request->path(),
            'method' => $request->method(),
            'request_body' => $request->getContent(),
            'status' => 'processing',
        ]);

        $response = $next($request);

        // Only cache successful responses
        if ($response->getStatusCode() < 400) {
            \App\Models\IdempotencyKey::where('key', $idempotencyKey)->update([
                'status' => 'completed',
                'response_code' => $response->getStatusCode(),
                'response_body' => $response->getContent(),
                'response_headers' => $response->headers->all(),
                'completed_at' => now(),
            ]);
        } else {
            // On error, remove the key so it can be retried
            \App\Models\IdempotencyKey::where('key', $idempotencyKey)->delete();
        }

        return $response->header('X-Idempotency-Key', $idempotencyKey);
    }
}