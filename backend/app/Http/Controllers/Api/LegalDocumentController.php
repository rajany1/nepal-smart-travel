<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LegalDocument;
use Illuminate\Http\JsonResponse;

class LegalDocumentController extends Controller
{
    /**
     * Public endpoint: get the active (published) document by type.
     */
    public function show(string $type): JsonResponse
    {
        $validTypes = array_keys(LegalDocument::types());
        if (!in_array($type, $validTypes)) {
            return response()->json(['success' => false, 'message' => 'Invalid document type.'], 404);
        }

        $doc = LegalDocument::getActive($type);

        if (!$doc) {
            // Return empty so mobile can show fallback
            return response()->json([
                'success' => true,
                'data' => [
                    'type' => $type,
                    'title' => LegalDocument::types()[$type] ?? $type,
                    'content' => '',
                    'version' => null,
                    'published_at' => null,
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'type' => $doc->type,
                'title' => $doc->title,
                'content' => $doc->content,
                'version' => $doc->version,
                'published_at' => $doc->published_at?->toISOString(),
            ],
        ]);
    }

    /**
     * Public endpoint: list all published documents (for sitemap / footer links).
     */
    public function index(): JsonResponse
    {
        $documents = LegalDocument::published()
            ->get()
            ->map(fn($doc) => [
                'type' => $doc->type,
                'title' => $doc->title,
                'version' => $doc->version,
                'published_at' => $doc->published_at?->toISOString(),
            ]);

        return response()->json([
            'success' => true,
            'data' => $documents,
        ]);
    }
}
