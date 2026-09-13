<?php

namespace App\Services;

use App\Services\AiProviders\GeminiService;
use App\Services\AiProviders\GroqService;
use App\Services\AiProviders\OllamaService;

/**
 * AI Provider Factory — يختار المزود المناسب تلقائياً.
 *
 * ترتيب الأولوية:
 * 1. Ollama (محلي — الأفضل لسوريا)
 * 2. Groq (مجاني وسريع)
 * 3. Gemini (الحالي)
 * 4. Fallback (null)
 *
 * الإعداد: AI_PROVIDER=ollama|groq|gemini|auto
 */
class AiProviderFactory
{
    private const PROVIDERS = [
        'ollama' => OllamaService::class,
        'groq' => GroqService::class,
        'gemini' => GeminiService::class,
    ];

    /**
     * Get the active AI provider instance.
     */
    public static function make(): GeminiService|GroqService|OllamaService|null
    {
        $provider = (string) config('ai.provider', 'auto');

        if ($provider === 'auto') {
            return self::autoDetect();
        }

        if (isset(self::PROVIDERS[$provider])) {
            $instance = app(self::PROVIDERS[$provider]);

            // Check availability for providers that need it
            if ($provider === 'ollama' && ! $instance->isAvailable()) {
                Log::channel('ai')->warning("AI provider {$provider} not available, falling back");
                return self::fallback();
            }

            if (in_array($provider, ['groq', 'gemini'], true) && ! $instance->isConfigured()) {
                Log::channel('ai')->warning("AI provider {$provider} not configured, falling back");
                return self::fallback();
            }

            return $instance;
        }

        return self::fallback();
    }

    /**
     * Auto-detect the best available provider.
     */
    private static function autoDetect(): GeminiService|GroqService|OllamaService|null
    {
        // 1. Try Ollama first (local, works offline)
        $ollama = app(OllamaService::class);
        if ($ollama->isAvailable()) {
            Log::channel('ai')->info('AI provider auto-selected: Ollama (local)');
            return $ollama;
        }

        // 2. Try Groq (free tier, fast)
        $groq = app(GroqService::class);
        if ($groq->isConfigured()) {
            Log::channel('ai')->info('AI provider auto-selected: Groq');
            return $groq;
        }

        // 3. Try Gemini (existing)
        $gemini = app(GeminiService::class);
        if ($gemini->isConfigured()) {
            Log::channel('ai')->info('AI provider auto-selected: Gemini');
            return $gemini;
        }

        Log::channel('ai')->warning('No AI provider available');
        return null;
    }

    /**
     * Fallback: try any configured provider.
     */
    private static function fallback(): GeminiService|GroqService|OllamaService|null
    {
        $ollama = app(OllamaService::class);
        if ($ollama->isAvailable()) {
            return $ollama;
        }

        $groq = app(GroqService::class);
        if ($groq->isConfigured()) {
            return $groq;
        }

        $gemini = app(GeminiService::class);
        if ($gemini->isConfigured()) {
            return $gemini;
        }

        return null;
    }

    /**
     * Get the name of the active provider.
     */
    public static function activeProviderName(): string
    {
        $provider = self::make();

        return match (true) {
            $provider instanceof OllamaService => 'Ollama (محلي)',
            $provider instanceof GroqService => 'Groq',
            $provider instanceof GeminiService => 'Gemini',
            default => 'غير متاح',
        };
    }
}
