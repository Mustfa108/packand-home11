<?php

return [
    'api_key' => env('GEMINI_API_KEY', ''),
    'model'   => env('GEMINI_MODEL', 'gemini-3.5-flash-lite'),
    // Request timeout in seconds. Keep below typical PHP/proxy limits so
    // Gemini failure becomes a rule-based fallback instead of a 500.
    'timeout' => env('GEMINI_TIMEOUT', 25),
    // Per-user AI rate limits.
    'analysis_attempts_per_day' => env('GEMINI_ANALYSIS_ATTEMPTS_PER_DAY', 10),
    'chat_messages_per_day'     => env('GEMINI_CHAT_MESSAGES_PER_DAY', 30),
    'chat_max_question_length'  => env('GEMINI_CHAT_MAX_QUESTION_LENGTH', 500),
];
