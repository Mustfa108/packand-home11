<?php

namespace App\Services;

use App\Models\SiteSetting;
use App\Support\GeminiHelpers;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class SiteSettingService
{
    public const KEY_GEMINI_API_KEY = 'gemini_api_key';

    public const KEY_GEMINI_MODEL = 'gemini_model';

    public const KEY_GEMINI_QUOTA_HIT_AT = 'gemini_quota_hit_at';

    public const KEY_GEMINI_QUOTA_MESSAGE = 'gemini_quota_last_message';

    public const KEY_GEMINI_QUOTA_HTTP = 'gemini_quota_last_http';

    public const KEY_GEMINI_QUOTA_STATUS = 'gemini_quota_last_status';

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

        return (string) config('gemini.model', 'gemini-3.6-flash');
    }

    /**
     * Remember the latest Gemini free-tier / rate-limit failure for the admin UI.
     */
    public function recordGeminiQuotaHit(?int $httpStatus, ?string $errorStatus, ?string $errorMessage): void
    {
        $safeMessage = GeminiHelpers::redactSecrets(trim((string) $errorMessage));

        $this->set(self::KEY_GEMINI_QUOTA_HIT_AT, now()->toIso8601String());
        $this->set(self::KEY_GEMINI_QUOTA_MESSAGE, mb_substr($safeMessage, 0, 500) ?: null);
        $this->set(self::KEY_GEMINI_QUOTA_HTTP, $httpStatus !== null ? (string) $httpStatus : null);
        $this->set(self::KEY_GEMINI_QUOTA_STATUS, $errorStatus ? mb_substr($errorStatus, 0, 80) : null);
    }

    public function clearGeminiQuotaHit(): void
    {
        $this->set(self::KEY_GEMINI_QUOTA_HIT_AT, null);
        $this->set(self::KEY_GEMINI_QUOTA_MESSAGE, null);
        $this->set(self::KEY_GEMINI_QUOTA_HTTP, null);
        $this->set(self::KEY_GEMINI_QUOTA_STATUS, null);
    }

    /**
     * Active for ~24h after a quota/rate-limit hit (typical free-tier daily reset window).
     *
     * @return array{active: bool, hit_at: string, http_status: ?int, error_status: ?string, last_error: ?string, message_ar: string, log_hint_ar: string}|null
     */
    public function geminiQuotaAlert(): ?array
    {
        $hitAtRaw = $this->get(self::KEY_GEMINI_QUOTA_HIT_AT);
        if (! $hitAtRaw) {
            return null;
        }

        try {
            $hitAt = Carbon::parse((string) $hitAtRaw);
        } catch (\Throwable) {
            return null;
        }

        if ($hitAt->lt(now()->subDay())) {
            return null;
        }

        $http = $this->get(self::KEY_GEMINI_QUOTA_HTTP);
        $errorStatus = $this->get(self::KEY_GEMINI_QUOTA_STATUS);
        $lastError = $this->get(self::KEY_GEMINI_QUOTA_MESSAGE);

        return [
            'active' => true,
            'hit_at' => $hitAt->toIso8601String(),
            'http_status' => $http !== null && $http !== '' ? (int) $http : null,
            'error_status' => $errorStatus ? (string) $errorStatus : null,
            'last_error' => $lastError ? (string) $lastError : null,
            'message_ar' => 'يبدو أن الحد اليومي أو حصة الطلبات المجانية لمفتاح Gemini قد استُنفدت. الميزات الذكية قد ترجع تحليلاً مبسّطاً حتى تُعاد تعبئة الحصة (عادةً خلال 24 ساعة) أو تستخدم مفتاحاً/خطة أعلى.',
            'log_hint_ar' => 'في لوج السيرفر (قناة ai) ابحث عن: status 429 أو RESOURCE_EXHAUSTED أو كلمات quota / rate limit / exceeded your current quota. خطأ 503 مع "high demand" ليس حداً يومياً بل ضغطاً مؤقتاً.',
        ];
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
