<?php

namespace App\Services\AiProviders;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Google Gemini AI Provider — الحالي في النظام.
 * يحتاج VPN في بعض الدول (مثل سوريا).
 */
class GeminiService
{
    private string $apiKey;

    private string $model;

    private int $timeout;

    public function __construct()
    {
        $this->apiKey = (string) config('ai.gemini_api_key', '');
        $this->model = (string) config('ai.gemini_model', 'gemini-2.0-flash');
        $this->timeout = (int) config('ai.gemini_timeout', 25);
    }

    /**
     * Check if Gemini is configured.
     */
    public function isConfigured(): bool
    {
        return $this->apiKey !== '' && $this->apiKey !== 'your_gemini_api_key_here';
    }

    /**
     * Generate text from a prompt.
     */
    public function generate(string $prompt, int $maxTokens = 2048): ?string
    {
        if (! $this->isConfigured()) {
            Log::channel('ai')->warning('Gemini API key missing');
            return null;
        }

        try {
            $response = Http::timeout($this->timeout)
                ->post("https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}", [
                    'contents' => [
                        ['parts' => [['text' => $prompt]]],
                    ],
                    'generationConfig' => [
                        'temperature' => 0.7,
                        'maxOutputTokens' => $maxTokens,
                    ],
                ]);

            if ($response->failed()) {
                Log::channel('ai')->error('Gemini request failed', [
                    'status' => $response->status(),
                ]);
                return null;
            }

            return $response->json('candidates.0.content.parts.0.text') ?: null;
        } catch (\Throwable $e) {
            Log::channel('ai')->error('Gemini exception', [
                'message' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Chat completion (Gemini doesn't have native chat, we simulate with context).
     *
     * @param array<int, array{role:string, content:string}> $messages
     */
    public function chat(array $messages, int $maxTokens = 1024): ?string
    {
        // Convert messages to a single prompt with context
        $prompt = collect($messages)->map(
            fn ($m) => strtoupper($m['role']).': '.$m['content']
        )->join("\n\n");

        return $this->generate($prompt, $maxTokens);
    }
}
