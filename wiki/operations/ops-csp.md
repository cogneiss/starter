---
title: Content Security Policy and form friction
status: current
supersedes: []
code_refs:
    - app/Http/Middleware/SetContentSecurityPolicy.php
    - app/Http/Middleware/ProtectPublicForm.php
    - tests/Feature/CspTest.php
    - tests/Browser/CspTest.php
    - tests/Feature/FormFrictionTest.php
updated: 2026-09-01
---

# Content Security Policy and form friction

## The CSP

`SetContentSecurityPolicy` puts an enforcing `Content-Security-Policy` header
on every HTML response, with a per-request nonce for scripts and styles — no
`unsafe-inline`, no `unsafe-eval`. Injected markup that reaches a page does
not execute; it fails the policy. The browser test in `tests/Browser/CspTest.php`
loads real pages under the enforced policy, which is how a library injecting
an inline stylesheet (as a toast library once did) gets caught as a broken
page rather than as a silently weakened policy.

The nonce reaches the Vite tags and Inertia's boot script through the
request; anything else inline is a build error to fix, not a directive to
loosen.

## Public form friction

`ProtectPublicForm` slows abuse of the unauthenticated forms (registration,
login, magic links): a honeypot field no human fills, a minimum time between
render and submit no script waits for, and throttling on top. All friction is
invisible to a person — no CAPTCHA — and a rejection is indistinguishable
from a validation error, so a bot learns nothing about which trap it hit.

Both are boot-time defaults, not opt-ins: removing either is an edit to the
middleware stack you would have to make on purpose.
