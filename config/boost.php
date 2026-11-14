<?php

declare(strict_types=1);

/**
 * Only the values that differ from Boost's defaults.
 */
return [
    /**
     * Left unset, Boost decides whether to emit its test-enforcement guideline
     * by shelling out to `artisan test --list-tests` and counting the results.
     * That makes the generated files depend on whether a subprocess happened to
     * succeed, so the drift gate in tests/Feature/Docs/GuidelinesAreCurrentTest
     * goes red for reasons that have nothing to do with the diff. Pinned to true
     * because this project does enforce tests.
     */
    'enforce_tests' => true,

    /**
     * Project rules live as tracked Markdown in .ai/rules/, with path-scoped
     * guidelines moved to .ai/rules/boost/.
     */
    'rules' => [
        'enabled' => env('BOOST_RULES_ENABLED', true),
        'scoped_guidelines' => env('BOOST_RULES_SCOPED_GUIDELINES', true),
    ],

    /**
     * Every section here loads on every turn, so a package section is dropped
     * when its skill already carries the same procedure and activates on its own
     * description. `deployments` has no deploy target here; `wayfinder/core` and
     * the activitylog section only restate what their skills say.
     */
    'guidelines' => [
        'exclude' => ['deployments', 'wayfinder/core', 'spatie/laravel-activitylog/core'],
    ],
];
