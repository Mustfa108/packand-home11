<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\SiteSettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminSettingsController extends Controller
{
    public function __construct(private SiteSettingService $settings) {}

    public function showAi(): JsonResponse
    {
        $key = $this->settings->geminiApiKey();

        return ApiResponse::success([
            'gemini_api_key_masked' => $this->settings->maskSecret($key),
            'gemini_api_key_set' => $key !== '' && $key !== 'your_gemini_api_key_here',
            'gemini_model' => $this->settings->geminiModel(),
            'provider' => 'gemini',
        ], 'تم تحميل إعدادات الذكاء الاصطناعي.');
    }

    public function updateAi(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'gemini_api_key' => ['nullable', 'string', 'max:500'],
            'gemini_model' => ['nullable', 'string', 'max:120'],
        ]);

        if (array_key_exists('gemini_api_key', $validated) && filled($validated['gemini_api_key'])) {
            $this->settings->set(SiteSettingService::KEY_GEMINI_API_KEY, $validated['gemini_api_key'], true);
        }

        if (array_key_exists('gemini_model', $validated) && filled($validated['gemini_model'])) {
            $this->settings->set(SiteSettingService::KEY_GEMINI_MODEL, $validated['gemini_model']);
        }

        return $this->showAi();
    }

    public function showSocial(): JsonResponse
    {
        return ApiResponse::success($this->settings->socialLinks(), 'تم تحميل روابط التواصل.');
    }

    public function updateSocial(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'facebook' => ['nullable', 'string', 'max:500'],
            'instagram' => ['nullable', 'string', 'max:500'],
            'twitter' => ['nullable', 'string', 'max:500'],
            'linkedin' => ['nullable', 'string', 'max:500'],
            'whatsapp' => ['nullable', 'string', 'max:500'],
            'youtube' => ['nullable', 'string', 'max:500'],
            'telegram' => ['nullable', 'string', 'max:500'],
        ]);

        $map = [
            'facebook' => SiteSettingService::KEY_SOCIAL_FACEBOOK,
            'instagram' => SiteSettingService::KEY_SOCIAL_INSTAGRAM,
            'twitter' => SiteSettingService::KEY_SOCIAL_TWITTER,
            'linkedin' => SiteSettingService::KEY_SOCIAL_LINKEDIN,
            'whatsapp' => SiteSettingService::KEY_SOCIAL_WHATSAPP,
            'youtube' => SiteSettingService::KEY_SOCIAL_YOUTUBE,
            'telegram' => SiteSettingService::KEY_SOCIAL_TELEGRAM,
        ];

        foreach ($map as $field => $key) {
            if (array_key_exists($field, $validated)) {
                $this->settings->set($key, $validated[$field] ?: null);
            }
        }

        return $this->showSocial();
    }
}
