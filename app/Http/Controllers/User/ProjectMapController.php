<?php

namespace App\Http\Controllers\User;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\ProjectReview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectMapController extends Controller
{
    /**
     * Public map of claimed/available project pins for authenticated users.
     */
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $pins = ProjectReview::query()
            ->where('is_public_on_map', true)
            ->whereNotNull('lat')
            ->whereNotNull('lng')
            ->latest()
            ->limit(500)
            ->get(['id', 'user_id', 'project_name', 'stage', 'location', 'lat', 'lng', 'is_claimed', 'ai_score', 'ai_level']);

        $items = $pins->map(function (ProjectReview $review) use ($userId) {
            $claimed = (bool) $review->is_claimed;
            $mine = (int) $review->user_id === (int) $userId;

            return [
                'id' => $review->id,
                'project_name' => $review->project_name,
                'stage' => $review->stage,
                'location' => $review->location,
                'lat' => (float) $review->lat,
                'lng' => (float) $review->lng,
                'is_claimed' => $claimed,
                'is_mine' => $mine,
                'status' => $mine ? 'mine' : ($claimed ? 'claimed' : 'available'),
                'ai_score' => $mine ? $review->ai_score : null,
                'ai_level' => $mine ? $review->ai_level : null,
            ];
        })->values();

        return ApiResponse::success(['items' => $items], 'تم تحميل خريطة المشاريع.');
    }
}
