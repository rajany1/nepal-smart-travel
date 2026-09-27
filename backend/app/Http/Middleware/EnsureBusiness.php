<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBusiness
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        if (! $user) {
            return redirect()->route('partner.login');
        }

        if (! $user->isBusiness()) {
            if ($request->expectsJson()) {
                abort(403, 'Business partners only.');
            }

            return redirect()->route('partner.login')
                ->with('error', 'This area is for business partners only.');
        }

        $partner = $user->business;
        if (! $partner) {
            return redirect()->route('partner.business-form')->with('error', 'Complete your business profile first.');
        }

        if ($partner->verification_status === 'pending') {
            return redirect()->route('partner.pending')->with('info', 'Your business is awaiting verification.');
        }

        if ($partner->verification_status === 'rejected') {
            return redirect()->route('partner.business-form')->with('error', $partner->rejected_reason ?? 'Your business application was rejected. Please update and resubmit.');
        }

        return $next($request);
    }
}
