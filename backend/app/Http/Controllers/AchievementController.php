<?php

namespace App\Http\Controllers;

use App\Services\AchievementService;
use Illuminate\Http\Request;

class AchievementController extends Controller
{
    public function __construct(
        private AchievementService $achievementService,
    ) {}

    public function index(Request $request)
    {
        $achievements = $this->achievementService->getUserAchievements($request->user());

        return response()->json([
            'success' => true,
            'data' => $achievements,
        ]);
    }

    public function xpHistory(Request $request)
    {
        $history = $this->achievementService->getXpHistory($request->user());

        return response()->json([
            'success' => true,
            'data' => $history->items(),
            'meta' => [
                'current_page' => $history->currentPage(),
                'last_page' => $history->lastPage(),
                'per_page' => $history->perPage(),
                'total' => $history->total(),
            ],
        ]);
    }
}
