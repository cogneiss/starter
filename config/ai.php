<?php

declare(strict_types=1);

use App\Ai\Actions\InviteMember;
use Laravel\Ai\Enums\Lab;

return [

    /*
    |--------------------------------------------------------------------------
    | Default AI Provider
    |--------------------------------------------------------------------------
    |
    | This file holds only the values that differ from laravel/ai's defaults,
    | plus the keys this application adds. The package merges its own config
    | underneath one level deep, so the provider list, their keys and the
    | per-modality defaults (images, audio, embeddings and so on) come from
    | vendor/laravel/ai/config/ai.php. Each top-level key here replaces the
    | package's array of the same name.
    |
    | An operation may also be given a list of providers instead of one name,
    | in which case the SDK fails over left to right:
    | `prompt('...', provider: [Lab::Anthropic, Lab::OpenAI])`. App\Support\AiTier
    | does this for the tier map below once both providers carry a key.
    |
    */

    'default' => env('AI_DEFAULT_PROVIDER', 'anthropic'),

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
    | `failover` is the second provider the SDK tries when the first one fails.
    | It only takes effect when both providers carry a key, so a checkout with
    | one key or none behaves exactly as before. Every model named here must
    | have an entry under `pricing`; tests/Feature/Ai/AiPricingTest.php fails
    | when one does not.
    |
    */

    'tiers' => [
        'cheap' => [
            'provider' => Lab::Anthropic,
            'model' => env('AI_MODEL_CHEAP', 'claude-haiku-5-5'),
            'failover' => [
                'provider' => Lab::OpenAI,
                'model' => env('AI_MODEL_CHEAP_FAILOVER', 'gpt-6-luna'),
            ],
        ],
        'smart' => [
            'provider' => Lab::Anthropic,
            'model' => env('AI_MODEL_SMART', 'claude-sonnet-5-5'),
            'failover' => [
                'provider' => Lab::OpenAI,
                'model' => env('AI_MODEL_SMART_FAILOVER', 'gpt-6-astra'),
            ],
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
        'user_requests_per_hour' => (int) env('AI_QUOTA_USER_HOUR', 60),
        'org_requests_per_day' => (int) env('AI_QUOTA_ORG_DAY', 2000),
        'org_budget_micros_per_month' => (int) env('AI_BUDGET_ORG_MONTH', 50_000_000),
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
        ), static fn (string $value): bool => (bool) $value)),
        'denied_topics' => array_values(array_filter(array_map(
            trim(...),
            explode(',', (string) env('AI_DENIED_TOPICS', '')),
        ), static fn (string $value): bool => (bool) $value)),
    ],

    /*
    |--------------------------------------------------------------------------
    | Confirmable Actions
    |--------------------------------------------------------------------------
    |
    | Agents never write. They propose one of the `ai.actions` keys below, and
    | the person is shown a confirmation that expires after `ttl` minutes and is
    | good for one use. The map is the allowlist: a class name coming out of a
    | model is never resolved, so the worst an injected prompt can do is name a
    | key that is already permitted, to a person who is already permitted to
    | run it.
    |
    */

    'confirm' => [
        'ttl' => (int) env('AI_CONFIRM_TTL', 15),
    ],

    'actions' => [
        // App\Ai\Actions\InviteMember is a face, not an implementation: the
        // write it performs on confirmation is App\Actions\CreateOrganizationInvitation,
        // the same action the invitation form posts to.
        'invite-member' => InviteMember::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Dashboard Briefing
    |--------------------------------------------------------------------------
    |
    | How long, in seconds, one organization's dashboard briefing is reused for.
    | The figures behind it move slowly and the briefing is rendered on every
    | dashboard load, so regenerating it per request buys nothing and is billed
    | per request. Read it as config('ai.briefing.ttl').
    |
    */

    'briefing' => [
        'ttl' => (int) env('AI_BRIEFING_TTL', 900),
    ],

    /*
    |--------------------------------------------------------------------------
    | Assistant Memory
    |--------------------------------------------------------------------------
    |
    | How many remembered facts one person may carry in one organization. The
    | cap is a prompt-size decision as much as a storage one: every fact is
    | read into the system prompt on every request, so an uncapped memory is a
    | bill that grows on its own. Past the cap the least recently touched fact
    | is dropped. Read it as config('ai.memory.max_facts').
    |
    */

    'memory' => [
        'max_facts' => (int) env('AI_MEMORY_MAX_FACTS', 20),
    ],

    /*
    |--------------------------------------------------------------------------
    | Model Pricing
    |--------------------------------------------------------------------------
    |
    | Micros per million tokens, per provider and model, where 1_000_000 is one
    | dollar per million. The Anthropic rows are the published Claude 5.5
    | prices. The OpenAI rows are set at the fallback ceiling because no
    | published price has been checked for them, so failover over-charges
    | rather than under-charges until someone confirms the real rate.
    |
    | A model that is not listed is billed at `pricing_fallback`, a deliberate
    | ceiling, so spend is never metered as zero. Overriding a tier model with
    | an env var is safe for the same reason.
    |
    */

    'pricing' => [
        'anthropic' => [
            'claude-haiku-5-5' => ['input' => 100_000, 'output' => 500_000],
            'claude-sonnet-5-5' => ['input' => 2_000_000, 'output' => 10_000_000],
            'claude-opus-5-5' => ['input' => 4_000_000, 'output' => 20_000_000],
        ],
        'openai' => [
            'gpt-6-luna' => ['input' => 4_000_000, 'output' => 20_000_000],
            'gpt-6-astra' => ['input' => 4_000_000, 'output' => 20_000_000],
        ],
    ],

    'pricing_fallback' => ['input' => 4_000_000, 'output' => 20_000_000],

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
            'cache' => true,
            'store' => env('CACHE_STORE', 'database'),
        ],
    ],

];
