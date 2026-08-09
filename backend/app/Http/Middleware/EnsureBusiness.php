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

        if (!$user || !$user->isBusiness()) {
            abort(403, 'Business partners only.');
        }

        $partner = $user->business;
        if (!$partner) {
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
