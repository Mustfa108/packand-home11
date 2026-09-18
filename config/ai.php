<?php

return [
    /*
    |--------------------------------------------------------------------------
    | AI Provider
    |--------------------------------------------------------------------------
    |
    | Supported: "auto", "ollama", "groq", "gemini"
    |
    | auto   = يختار تلقائياً: Ollama → Groq → Gemini
    | ollama = محلي، يعمل بدون إنترنت (الأفضل لسوريا)
    | groq   = free tier سريع (https://console.groq.com)
    | gemini = Google Gemini (يحتاج VPN في بعض الدول)
    |
    */
    'provider' => env('AI_PROVIDER', 'auto'),

    /*
    |--------------------------------------------------------------------------
    | Ollama (Local AI)
    |--------------------------------------------------------------------------
    |
    | يتطلب تثبيت Ollama محلياً: https://ollama.com
    |
    | 1. تثبيت Ollama
    | 2. سحب نموذج: ollama pull llama3
    | 3. تشغيل الخادم: ollama serve
    |
    */
    'ollama_base_url' => env('OLLAMA_BASE_URL', 'http://localhost:11434'),
    'ollama_model' => env('OLLAMA_MODEL', 'llama3'),
    'ollama_timeout' => env('OLLAMA_TIMEOUT', 120),

    /*
    |--------------------------------------------------------------------------
    | Groq (Fast Cloud AI)
    |--------------------------------------------------------------------------
    |
    | سجل في: https://console.groq.com
    | الموديلات المجانية: llama3-8b-8192, mixtral-8x7b-32768
    |
    */
    'groq_api_key' => env('GROQ_API_KEY', ''),
    'groq_model' => env('GROQ_MODEL', 'llama3-8b-8192'),
    'groq_timeout' => env('GROQ_TIMEOUT', 45),

    /*
    |--------------------------------------------------------------------------
    | Google Gemini
    |--------------------------------------------------------------------------
    |
    | سجل في: https://aistudio.google.com
    |
    */
    'gemini_api_key' => env('GEMINI_API_KEY', ''),
    'gemini_model' => env('GEMINI_MODEL', 'gemini-3.6-flash'),
    'gemini_timeout' => env('GEMINI_TIMEOUT', 25),

    /*
    |--------------------------------------------------------------------------
    | Rate Limits
    |--------------------------------------------------------------------------
    */
    'analysis_attempts_per_day' => env('AI_ANALYSIS_ATTEMPTS_PER_DAY', 10),
    'chat_messages_per_day' => env('AI_CHAT_MESSAGES_PER_DAY', 30),
    'chat_max_question_length' => env('AI_CHAT_MAX_QUESTION_LENGTH', 500),
];
