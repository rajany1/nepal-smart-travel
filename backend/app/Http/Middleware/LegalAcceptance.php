<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\LegalDocumentAcceptance;
use Symfony\Component\HttpFoundation\Response;

class LegalAcceptance
{
    /**
     * Block authenticated users who need to (re-)accept current legal documents.
     *
     * Returns 403 with code LEGAL_RE_ACCEPTANCE_REQUIRED when the user has
     * not yet accepted the latest published version of terms_conditions or
     * privacy_policy.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return $next($request);
        }

        $requiredTypes = ['terms_conditions', 'privacy_policy'];

        foreach ($requiredTypes as $type) {
            if (LegalDocumentAcceptance::needsReAcceptance($user, $type)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Updated legal documents require your acceptance.',
                    'code' => 'LEGAL_RE_ACCEPTANCE_REQUIRED',
                ], 403);
            }
        }

        return $next($request);
    }
}
