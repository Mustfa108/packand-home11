<?php

return [
    'api_key' => env('GEMINI_API_KEY', ''),
    // Production-proven model (older 1.5 / 2.5 flash variants return 404 for new keys).
    'model'   => env('GEMINI_MODEL', 'gemini-3.6-flash'),
    // Request timeout in seconds. Analysis prompts need more headroom than chat.
    'timeout' => env('GEMINI_TIMEOUT', 45),
    'max_retries' => env('GEMINI_MAX_RETRIES', 2),
    'retry_sleep_ms' => env('GEMINI_RETRY_SLEEP_MS', 800),
    // Per-user AI rate limits.
    'analysis_attempts_per_day' => env('GEMINI_ANALYSIS_ATTEMPTS_PER_DAY', 10),
    'chat_messages_per_day'     => env('GEMINI_CHAT_MESSAGES_PER_DAY', 30),
    'chat_max_question_length'  => env('GEMINI_CHAT_MAX_QUESTION_LENGTH', 500),
    // Enough headroom for a complete Arabic answer; prompt still discourages filler.
    'chat_max_output_tokens'    => env('GEMINI_CHAT_MAX_OUTPUT_TOKENS', 1536),
    'analysis_max_output_tokens' => env('GEMINI_ANALYSIS_MAX_OUTPUT_TOKENS', 4096),
];
