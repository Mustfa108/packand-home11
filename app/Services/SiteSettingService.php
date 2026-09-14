<?php

namespace App\Services;

use App\Models\SiteSetting;
use Illuminate\Support\Facades\Cache;

class SiteSettingService
{
    public const KEY_GEMINI_API_KEY = 'gemini_api_key';

    public const KEY_GEMINI_MODEL = 'gemini_model';

    public const KEY_SOCIAL_FACEBOOK = 'social_facebook';

    public const KEY_SOCIAL_INSTAGRAM = 'social_instagram';

    public const KEY_SOCIAL_TWITTER = 'social_twitter';

    public const KEY_SOCIAL_LINKEDIN = 'social_linkedin';

    public const KEY_SOCIAL_WHATSAPP = 'social_whatsapp';

    public const KEY_SOCIAL_YOUTUBE = 'social_youtube';

    public const KEY_SOCIAL_TELEGRAM = 'social_telegram';

    private const CACHE_TTL = 60;

    public function get(string $key, mixed $default = null): mixed
    {
        return Cache::remember("site_setting:{$key}", self::CACHE_TTL, function () use ($key, $default) {
            $row = SiteSetting::query()->where('key', $key)->first();

            if (! $row) {
                return $default;
            }

            return $row->plain_value ?? $default;
        });
    }

    public function set(string $key, ?string $value, bool $encrypt = false): void
    {
        SiteSetting::put($key, $value, $encrypt);
        Cache::forget("site_setting:{$key}");
    }

    public function geminiApiKey(): string
    {
        $fromDb = (string) ($this->get(self::KEY_GEMINI_API_KEY, '') ?? '');

        if ($fromDb !== '' && $fromDb !== 'your_gemini_api_key_here') {
            return $fromDb;
        }

        return (string) config('gemini.api_key', '');
    }

    public function geminiModel(): string
    {
        $fromDb = (string) ($this->get(self::KEY_GEMINI_MODEL, '') ?? '');

        if ($fromDb !== '') {
            return $fromDb;
        }

        return (string) config('gemini.model', 'gemini-2.0-flash');
    }

    public function socialLinks(): array
    {
        return [
            'facebook' => $this->get(self::KEY_SOCIAL_FACEBOOK),
            'instagram' => $this->get(self::KEY_SOCIAL_INSTAGRAM),
            'twitter' => $this->get(self::KEY_SOCIAL_TWITTER),
            'linkedin' => $this->get(self::KEY_SOCIAL_LINKEDIN),
            'whatsapp' => $this->get(self::KEY_SOCIAL_WHATSAPP),
            'youtube' => $this->get(self::KEY_SOCIAL_YOUTUBE),
            'telegram' => $this->get(self::KEY_SOCIAL_TELEGRAM),
        ];
    }

    public function maskSecret(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $len = mb_strlen($value);
        if ($len <= 8) {
            return str_repeat('*', $len);
        }

        return mb_substr($value, 0, 4).str_repeat('*', max(4, $len - 8)).mb_substr($value, -4);
    }
}
