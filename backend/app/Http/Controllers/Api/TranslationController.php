<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TranslationGlossary;

class TranslationController extends Controller
{
    /**
     * Full active glossary as term => nepali map.
     * Used by the mobile app as its UI translation dictionary
     * (English fallback lives in the app itself).
     */
    public function dictionary()
    {
        $dictionary = TranslationGlossary::where('is_active', true)
            ->pluck('nepali', 'term');

        return response()->json([
            'success' => true,
            'data' => $dictionary,
        ]);
    }
}
