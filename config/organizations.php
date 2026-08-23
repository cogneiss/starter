<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Strict Scoping
    |--------------------------------------------------------------------------
    |
    | When true, querying an organization-scoped model without a resolved
    | organization throws instead of returning rows. Only turn this off while
    | migrating an existing database, and turn it back on afterwards.
    |
    */

    'strict' => env('ORGANIZATIONS_STRICT', true),

    /*
    |--------------------------------------------------------------------------
    | Resolver
    |--------------------------------------------------------------------------
    |
    | How the current organization is resolved for a request.
    |
    | Supported: "session", "subdomain", "single"
    |
    */

    'resolver' => env('ORGANIZATIONS_RESOLVER', 'session'),

];
