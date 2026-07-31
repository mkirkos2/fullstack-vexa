<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default AI Provider
    |--------------------------------------------------------------------------
    |
    | This option controls the default AI provider that will be used
    | throughout the application.
    |
    */

    'default' => env('AI_PROVIDER', 'groq'),

    /*
    |--------------------------------------------------------------------------
    | AI Providers
    |--------------------------------------------------------------------------
    |
    | Here you may configure as many AI providers as needed.
    |
    */

    'providers' => [
        'groq' => [
            'base_url' => env('GROQ_BASE_URL', 'https://api.groq.com/openai/v1'),
            'api_key' => env('GROQ_API_KEY'),
            'model' => env('GROQ_MODEL', 'llama-3.1-8b-instant'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Request Configuration
    |--------------------------------------------------------------------------
    |
    | These options configure the HTTP request behavior.
    |
    */

    'timeout' => env('AI_TIMEOUT', 30),
    'connect_timeout' => env('AI_CONNECT_TIMEOUT', 10),

    /*
    |--------------------------------------------------------------------------
    | Generation Configuration
    |--------------------------------------------------------------------------
    |
    | These options configure the AI generation behavior.
    |
    */

    'max_tokens' => env('AI_MAX_TOKENS', 1024),
    'temperature' => env('AI_TEMPERATURE', 0.7),
];
