<?php

declare(strict_types=1);

// A Sentry event without a release cannot be traced to the commit that threw
// it, so with a DSN and no SENTRY_RELEASE the checked-out commit stands in.
// Known limit: git runs whenever this file loads uncached (once at config:cache in
// production); an image built without .git must set SENTRY_RELEASE itself.
$checkedOutCommit = filled(env('SENTRY_DSN')) && blank(env('SENTRY_RELEASE'))
    ? mb_trim((string) shell_exec('git -C '.escapeshellarg(base_path()).' rev-parse --verify --quiet HEAD 2>/dev/null'))
    : '';

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID', ''),
        'client_secret' => env('GOOGLE_CLIENT_SECRET', ''),
        'redirect' => '/auth/google/callback',
    ],

    'github' => [
        'client_id' => env('GITHUB_CLIENT_ID', ''),
        'client_secret' => env('GITHUB_CLIENT_SECRET', ''),
        'redirect' => '/auth/github/callback',
    ],

    'posthog' => [
        'key' => filled(env('POSTHOG_KEY')) ? env('POSTHOG_KEY') : null,
        'host' => filled(env('POSTHOG_HOST')) ? env('POSTHOG_HOST') : 'https://app.posthog.com',
    ],

    'sentry' => [
        'dsn' => filled(env('SENTRY_DSN')) ? env('SENTRY_DSN') : null,
        'release' => match (true) {
            filled(env('SENTRY_RELEASE')) => env('SENTRY_RELEASE'),
            $checkedOutCommit !== '' => $checkedOutCommit,
            default => null,
        },
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
