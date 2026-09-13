<?php

namespace App\Services\AiProviders;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Groq AI Provider — free tier سريع جداً.
 * يستخدم OpenAI-compatible API مع سرعة استجابة فائقة.
 *
 * التسجيل: https://console.groq.com
 * الموديلات المجانية: llama3-8b-8192, mixtral-8x7b-32768, gemma-7b-it
 */
class GroqService
{
    private string $apiKey;

    private string $model;

    private int $timeout;

    private const API_URL = 'https://api.groq.com/openai/v1/chat/completions';

    public function __construct()
    {
        $this->apiKey = (string) config('ai.groq_api_key', '');
        $this->model = (string) config('ai.groq_model', 'llama3-8b-8192');
        $this->timeout = (int) config('ai.groq_timeout', 45);
    }

    /**
     * Check if Groq is configured.
     */
    public function isConfigured(): bool
    {
        return $this->apiKey !== '' && $this->apiKey !== 'your_groq_api_key_here';
    }

    /**
     * Generate text from a single prompt.
     */
    public function generate(string $prompt, int $maxTokens = 2048): ?string
    {
        return $this->chat([
            ['role' => 'system', 'content' => 'أنت مساعد ذكي محترف. أجب باللغة العربية الفصحى بأسلوب واضح ومختصر.'],
            ['role' => 'user', 'content' => $prompt],
        ], $maxTokens);
    }

    /**
     * Chat completion with message history.
     *
     * @param array<int, array{role:string, content:string}> $messages
     */
    public function chat(array $messages, int $maxTokens = 1024): ?string
    {
        if (! $this->isConfigured()) {
            Log::channel('ai')->warning('Groq API key missing');
            return null;
        }

        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ])
                ->post(self::API_URL, [
                    'model' => $this->model,
                    'messages' => $messages,
                    'temperature' => 0.7,
                    'max_tokens' => $maxTokens,
                ]);

            if ($response->failed()) {
                Log::channel('ai')->error('Groq request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return null;
            }

            return $response->json('choices.0.message.content') ?: null;
        } catch (\Throwable $e) {
            Log::channel('ai')->error('Groq exception', [
                'message' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
