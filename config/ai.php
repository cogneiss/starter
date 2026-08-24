<?php

declare(strict_types=1);

use Laravel\Ai\Enums\Lab;

return [

    /*
    |--------------------------------------------------------------------------
    | Default AI Provider Names
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the AI providers below should be the
    | default for AI operations when no explicit provider is provided
    | for the operation. This should be any provider defined below.
    |
    | An operation may also be given a list of providers instead of one name,
    | in which case the SDK fails over left to right:
    | `prompt('...', provider: [Lab::Anthropic, Lab::OpenAI])`. The tier map
    | below does this automatically once a second key is present.
    |
    */

    'default' => env('AI_DEFAULT_PROVIDER', 'anthropic'),
    'default_for_images' => 'gemini',
    'default_for_audio' => 'openai',
    'default_for_transcription' => 'openai',
    'default_for_embeddings' => 'openai',
    'default_for_reranking' => 'cohere',

    /*
    |--------------------------------------------------------------------------
    | Fake Gateway
    |--------------------------------------------------------------------------
    |
    | With no provider key configured, every agent is answered by the fake
    | gateway so that a fresh checkout boots and demos without an account
    | anywhere. Set AI_FAKE=true to pin that behaviour on a machine that does
    | have keys. See App\Support\AiAvailability.
    |
    */

    'fake' => (bool) env('AI_FAKE', false),

    /*
    |--------------------------------------------------------------------------
    | Default Model Tier
    |--------------------------------------------------------------------------
    |
    | The tier agents fall back to when they express no preference. Reported by
    | `php artisan app:doctor`.
    |
    */

    'default_tier' => env('AI_DEFAULT_TIER', 'cheap'),

    /*
    |--------------------------------------------------------------------------
    | Model Tiers
    |--------------------------------------------------------------------------
    |
    | Agents pick a tier, never a model name, so that swapping a model is one
    | edit here instead of a sweep through app/Ai/Agents. Resolve a tier with
    | App\Support\AiTier::for('cheap').
    |
    */

    'tiers' => [
        'cheap' => [
            'provider' => Lab::Anthropic,
            'model' => env('AI_MODEL_CHEAP', 'claude-haiku-4-5-20251001'),
        ],
        'smart' => [
            'provider' => Lab::Anthropic,
            'model' => env('AI_MODEL_SMART'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Quotas and Budget
    |--------------------------------------------------------------------------
    |
    | Counted against ai_audit_logs and the credit ledger by App\Support\AiQuota
    | and enforced by App\Ai\Middleware\EnforceQuota before a prompt reaches a
    | provider. The budget is integer micros: 50_000_000 is fifty dollars.
    |
    */

    'quotas' => [
        'user_requests_per_hour' => env('AI_QUOTA_USER_HOUR', 60),
        'org_requests_per_day' => env('AI_QUOTA_ORG_DAY', 2000),
        'org_budget_micros_per_month' => env('AI_BUDGET_ORG_MONTH', 50_000_000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Guardrails
    |--------------------------------------------------------------------------
    |
    | Comma separated, both empty by default. `egress` is the exact list of
    | hosts App\Support\AiEgress lets an agent reach — membership, not a domain
    | pattern, and an empty list means an agent reaches nothing at all.
    | `denied_topics` are substrings App\Ai\Middleware\FilterTopics refuses a
    | prompt for before it costs anything.
    |
    */

    'guardrails' => [
        'egress' => array_values(array_filter(array_map(
            trim(...),
            explode(',', (string) env('AI_EGRESS_ALLOWLIST', '')),
        ))),
        'denied_topics' => array_values(array_filter(array_map(
            trim(...),
            explode(',', (string) env('AI_DENIED_TOPICS', '')),
        ))),
    ],

    /*
    |--------------------------------------------------------------------------
    | Model Pricing
    |--------------------------------------------------------------------------
    |
    | Micros per million tokens, per provider and model — 1_000_000 is one
    | dollar per million. A model that is not listed costs zero and is recorded
    | as such: App\Support\AiPricing never guesses a price into the ledger.
    |
    */

    'pricing' => [
        'anthropic' => [
            'claude-haiku-4-5-20251001' => ['input' => 1_000_000, 'output' => 5_000_000],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Caching
    |--------------------------------------------------------------------------
    |
    | Below you may configure caching strategies for AI related operations
    | such as embedding generation. You are free to adjust these values
    | based on your application's available caching stores and needs.
    |
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
    | Below are each of your AI providers defined for this application. Each
    | represents an AI provider and API key combination which can be used
    | to perform tasks like text, image, and audio creation via agents.
    |
    */

    'providers' => [
        'anthropic' => [
            'driver' => 'anthropic',
            'key' => env('ANTHROPIC_API_KEY'),
            'url' => env('ANTHROPIC_URL', 'https://api.anthropic.com/v1'),
        ],

        'azure' => [
            'driver' => 'azure',
            'key' => env('AZURE_OPENAI_API_KEY'),
            'url' => env('AZURE_OPENAI_URL'),
            'api_version' => env('AZURE_OPENAI_API_VERSION', '2025-04-01-preview'),
            'deployment' => env('AZURE_OPENAI_DEPLOYMENT', 'gpt-4o'),
            'embedding_deployment' => env('AZURE_OPENAI_EMBEDDING_DEPLOYMENT', 'text-embedding-3-small'),
            'image_deployment' => env('AZURE_OPENAI_IMAGE_DEPLOYMENT', 'gpt-image-1'),
            'store' => env('AZURE_OPENAI_STORE', true),
        ],

        'bedrock' => [
            'driver' => 'bedrock',
            'region' => env('AWS_BEDROCK_REGION', 'us-east-1'),
            'key' => env('AWS_BEARER_TOKEN_BEDROCK'),
            'access_key_id' => env('AWS_ACCESS_KEY_ID'),
            'secret_access_key' => env('AWS_SECRET_ACCESS_KEY'),
            'session_token' => env('AWS_SESSION_TOKEN'),
            'use_default_credential_provider' => env('AWS_USE_DEFAULT_CREDENTIALS', true),
            'assume_role' => [
                'arn' => env('AWS_BEDROCK_ASSUME_ROLE_ARN'),
                'session_name' => env('AWS_BEDROCK_ASSUME_ROLE_SESSION_NAME'),
                'duration_seconds' => env('AWS_BEDROCK_ASSUME_ROLE_DURATION_SECONDS'),
                'external_id' => env('AWS_BEDROCK_ASSUME_ROLE_EXTERNAL_ID'),
            ],
        ],

        'cohere' => [
            'driver' => 'cohere',
            'key' => env('COHERE_API_KEY'),
        ],

        'deepseek' => [
            'driver' => 'deepseek',
            'key' => env('DEEPSEEK_API_KEY'),
        ],

        'eleven' => [
            'driver' => 'eleven',
            'key' => env('ELEVENLABS_API_KEY'),
        ],

        'gemini' => [
            'driver' => 'gemini',
            'key' => env('GEMINI_API_KEY'),
            'url' => env('GEMINI_URL', 'https://generativelanguage.googleapis.com/v1beta/'),
        ],

        'groq' => [
            'driver' => 'groq',
            'key' => env('GROQ_API_KEY'),
        ],

        'jina' => [
            'driver' => 'jina',
            'key' => env('JINA_API_KEY'),
        ],

        'mistral' => [
            'driver' => 'mistral',
            'key' => env('MISTRAL_API_KEY'),
        ],

        'ollama' => [
            'driver' => 'ollama',
            'key' => env('OLLAMA_API_KEY', ''),
            'url' => env('OLLAMA_URL', 'http://localhost:11434'),
        ],

        'openai' => [
            'driver' => 'openai',
            'key' => env('OPENAI_API_KEY'),
            'url' => env('OPENAI_URL', 'https://api.openai.com/v1'),
            'store' => env('OPENAI_STORE', true),
        ],

        'openai-compatible' => [
            'driver' => 'openai-compatible',
            'url' => env('OPENAI_COMPATIBLE_URL'),
            'key' => env('OPENAI_COMPATIBLE_API_KEY'),
        ],

        'openrouter' => [
            'driver' => 'openrouter',
            'key' => env('OPENROUTER_API_KEY'),
        ],

        'voyageai' => [
            'driver' => 'voyageai',
            'key' => env('VOYAGEAI_API_KEY'),
        ],

        'xai' => [
            'driver' => 'xai',
            'key' => env('XAI_API_KEY'),
        ],
    ],

];
