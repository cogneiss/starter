<?php

declare(strict_types=1);

/**
 * Real provider keys are exported into the shell on plenty of machines, and the
 * blocking suite must never see one: every AI test asserts the zero-key path,
 * and a key that leaked in would send a stray prompt to a real provider.
 *
 * phpunit.xml's `<env force>` only reaches getenv() and $_ENV, while Laravel
 * reads $_SERVER first, so the keys are cleared from all three here — before
 * the framework boots and reads them.
 */
foreach (['ANTHROPIC_API_KEY', 'OPENAI_API_KEY', 'GEMINI_API_KEY'] as $key) {
    putenv($key.'=');
    $_ENV[$key] = '';
    $_SERVER[$key] = '';
}

require __DIR__.'/../vendor/autoload.php';
