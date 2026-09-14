<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Services\SiteSettingService;
use Illuminate\Http\JsonResponse;

class PublicSettingsController extends Controller
{
    public function socialLinks(SiteSettingService $settings): JsonResponse
    {
        $links = collect($settings->socialLinks())
            ->filter(fn ($url) => filled($url))
            ->all();

        return ApiResponse::success($links, 'تم تحميل روابط التواصل.');
    }
}
