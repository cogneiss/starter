<?php

declare(strict_types=1);

/*
 * Only the ssr key is published. The package merges its config one top-level
 * key deep, so this array replaces the vendor one whole and must list every
 * ssr option the vendor file does.
 */
return [

    'ssr' => [

        'enabled' => (bool) env('INERTIA_SSR_ENABLED'),

        'runtime' => is_string($runtime = env('INERTIA_SSR_RUNTIME')) && $runtime !== '' ? $runtime : 'node',

        'ensure_runtime_exists' => (bool) env('INERTIA_SSR_ENSURE_RUNTIME_EXISTS'),

        'url' => is_string($url = env('INERTIA_SSR_URL')) && $url !== '' ? $url : 'http://127.0.0.1:13714',

        'hot_url' => env('INERTIA_SSR_HOT_URL'),

        'timeout' => env('INERTIA_SSR_TIMEOUT'),

        'ensure_bundle_exists' => ! in_array(env('INERTIA_SSR_ENSURE_BUNDLE_EXISTS'), [false, '0'], true),

        'throw_on_error' => (bool) env('INERTIA_SSR_THROW_ON_ERROR'),

    ],

];
