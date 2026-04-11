<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default AI Provider
    |--------------------------------------------------------------------------
    |
    | The default provider for text generation. Cashbird uses the OpenAI-
    | compatible driver pointed at Cloudflare Workers AI through AI Gateway.
    | Override with AI_PROVIDER to use a different provider (e.g., anthropic).
    |
    */

    'default' => env('AI_PROVIDER', 'cloudflare'),
    'default_for_embeddings' => env('AI_EMBEDDING_PROVIDER', 'cloudflare'),

    /*
    |--------------------------------------------------------------------------
    | Caching
    |--------------------------------------------------------------------------
    */

    'caching' => [
        'embeddings' => [
            'cache' => false,
            'store' => env('CACHE_STORE', 'database'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Providers
    |--------------------------------------------------------------------------
    |
    | Cloudflare Workers AI is the primary provider, accessed through AI
    | Gateway for caching, analytics, rate limiting, and fallback routing.
    | The OpenAI driver is used since Workers AI exposes an OpenAI-compatible
    | API. Model tiers control which models are used for different tasks:
    |
    |   smartest  → agentic agents (QueryAgent, InsightsAgent, ReportAgent)
    |   cheapest  → classification agents (CategorizationAgent, BudgetAgent)
    |   default   → fallback for anything without a tier attribute
    |
    */

    'providers' => [
        'cloudflare' => [
            'driver' => 'openai',
            'key' => env('CLOUDFLARE_AI_API_TOKEN'),
            'url' => env('CLOUDFLARE_AI_GATEWAY_URL'),
            'models' => [
                'text' => [
                    'default' => env('AI_MODEL_DEFAULT', '@cf/zhipu/glm-4.7-flash'),
                    'smartest' => env('AI_MODEL_SMARTEST', '@cf/zhipu/glm-4.7-flash'),
                    'cheapest' => env('AI_MODEL_CHEAPEST', '@cf/google/gemma-4-26b-a4b-it'),
                ],
                'embeddings' => [
                    'default' => env('AI_EMBEDDING_MODEL', '@cf/baai/bge-base-en-v1.5'),
                    'dimensions' => (int) env('AI_EMBEDDING_DIMENSIONS', 768),
                ],
            ],
        ],

        'anthropic' => [
            'driver' => 'anthropic',
            'key' => env('ANTHROPIC_API_KEY'),
            'url' => env('ANTHROPIC_URL', 'https://api.anthropic.com/v1'),
        ],

        'openai' => [
            'driver' => 'openai',
            'key' => env('OPENAI_API_KEY'),
            'url' => env('OPENAI_URL', 'https://api.openai.com/v1'),
        ],

        'ollama' => [
            'driver' => 'ollama',
            'key' => env('OLLAMA_API_KEY', ''),
            'url' => env('OLLAMA_BASE_URL', 'http://localhost:11434'),
        ],
    ],

];
