<?php

return [
    'prism_server' => [
        'middleware' => [],
        'enabled' => env('PRISM_SERVER_ENABLED', false),
    ],

    'request_timeout' => env('PRISM_REQUEST_TIMEOUT', 60),

    'providers' => [
        // Cloudflare Workers AI through AI Gateway (OpenAI-compatible)
        'openai' => [
            'url' => env('CLOUDFLARE_AI_GATEWAY_URL', 'https://api.openai.com/v1'),
            'api_key' => env('CLOUDFLARE_AI_API_TOKEN', env('OPENAI_API_KEY', '')),
            'organization' => null,
            'project' => null,
        ],

        'anthropic' => [
            'api_key' => env('ANTHROPIC_API_KEY', ''),
            'version' => env('ANTHROPIC_API_VERSION', '2023-06-01'),
            'url' => env('ANTHROPIC_URL', 'https://api.anthropic.com/v1'),
            'default_thinking_budget' => env('ANTHROPIC_DEFAULT_THINKING_BUDGET', 1024),
            'anthropic_beta' => env('ANTHROPIC_BETA', null),
        ],

        'ollama' => [
            'url' => env('OLLAMA_URL', 'http://localhost:11434'),
        ],
    ],
];
