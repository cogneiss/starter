<?php

declare(strict_types=1);

use ComposerUnused\ComposerUnused\Configuration\Configuration;
use ComposerUnused\ComposerUnused\Configuration\NamedFilter;

return static function (Configuration $config): Configuration {
    return $config
        // Wayfinder ships an artisan command and a Vite plugin. Nothing in
        // `app/` imports one of its classes, so static analysis cannot see it.
        ->addNamedFilter(NamedFilter::fromString('laravel/wayfinder'))
        // Essentials is a set of framework-wide defaults applied from its
        // service provider (strict models, immutable dates, forced HTTPS). It
        // is used precisely by never being referenced.
        ->addNamedFilter(NamedFilter::fromString('nunomaduro/essentials'))
        // Reverb is the websocket server the broadcast connection points at. It
        // is configured and run, never imported, so nothing in `app/` names it.
        ->addNamedFilter(NamedFilter::fromString('laravel/reverb'));
};
