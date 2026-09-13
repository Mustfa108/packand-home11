<?php

namespace App\Services\AiProviders;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Ollama AI Provider — يعمل محلياً بدون إنترنت.
 * الأفضل للبيئات التي لا تملك وصولاً مستقراً للإنترنت (مثل سوريا).
 *
 * المتطلبات: تثبيت Ollama + نموذج عربي (مثل: llama3, qwen2, phi3)
 * التثبيت: https://ollama.com
 *
 * Example:
 *   ollama pull llama3
 *   ollama serve
 */
class OllamaService
{
    private string $baseUrl;

    private string $model;

    private int $timeout;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('ai.ollama_base_url', 'http://localhost:11434'), '/');
        $this->model = (string) config('ai.ollama_model', 'llama3');
        $this->timeout = (int) config('ai.ollama_timeout', 120);
    }

    /**
     * Check if Ollama is available.
     */
    public function isAvailable(): bool
    {
        try {
            $response = Http::timeout(5)->get("{$this->baseUrl}/api/tags");
            return $response->successful();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Generate text from a prompt.
     */
    public function generate(string $prompt, int $maxTokens = 2048): ?string
    {
        try {
            $response = Http::timeout($this->timeout)
                ->post("{$this->baseUrl}/api/generate", [
                    'model' => $this->model,
                    'prompt' => $prompt,
                    'stream' => false,
                    'options' => [
                        'temperature' => 0.7,
                        'num_predict' => $maxTokens,
                    ],
                ]);

            if ($response->failed()) {
                Log::channel('ai')->error('Ollama request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return null;
            }

            $text = $response->json('response');
            return $text ? trim($text) : null;
        } catch (\Throwable $e) {
            Log::channel('ai')->error('Ollama exception', [
                'message' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Chat completion with history.
     *
     * @param array<int, array{role:string, content:string}> $messages
     */
    public function chat(array $messages, int $maxTokens = 1024): ?string
    {
        try {
            $response = Http::timeout($this->timeout)
                ->post("{$this->baseUrl}/api/chat", [
                    'model' => $this->model,
                    'messages' => $messages,
                    'stream' => false,
                    'options' => [
                        'temperature' => 0.7,
                        'num_predict' => $maxTokens,
                    ],
                ]);

            if ($response->failed()) {
                Log::channel('ai')->error('Ollama chat failed', [
                    'status' => $response->status(),
                ]);
                return null;
            }

            return $response->json('message.content') ?: null;
        } catch (\Throwable $e) {
            Log::channel('ai')->error('Ollama chat exception', [
                'message' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * List available local models.
     *
     * @return array<int, string>
     */
    public function listModels(): array
    {
        try {
            $response = Http::timeout(10)->get("{$this->baseUrl}/api/tags");
            if ($response->failed()) {
                return [];
            }

            $models = $response->json('models') ?? [];
            return collect($models)->pluck('name')->filter()->values()->all();
        } catch (\Throwable $e) {
            return [];
        }
    }
}
