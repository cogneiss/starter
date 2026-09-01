<?php

declare(strict_types=1);

/**
 * The strings the interface renders on the client.
 *
 * PHP translation files stay canonical: this array is what the request hands to
 * the page as a prop, so a string has one home and one spelling rather than one
 * in a Blade view, one in a component and one nobody remembers writing.
 */
return [
    'language' => 'Language',

    'locale' => [
        'en' => 'English',
        'nl' => 'Nederlands',
    ],

    'nav' => [
        'admin' => 'Admin',
        'dashboard' => 'Dashboard',
        'documentation' => 'Documentation',
        'repository' => 'Repository',
    ],
];
