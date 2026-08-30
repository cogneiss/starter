---
title: Forms and live validation
status: current
supersedes: []
code_refs:
    - app/Support/PrecognitionAllowlist.php
    - routes/web.php
    - resources/js/pages/user-profile/edit.tsx
    - resources/js/components/input-error.tsx
    - tests/Feature/PrecognitionParityTest.php
    - tests/Feature/PrecognitionRequestTest.php
    - tests/Unit/PrecognitionAllowlistTest.php
    - tests/Mutations/phase11-parity.patch
    - tests/Mutations/phase11-allowlist.patch
updated: 2026-08-31
---

# Forms and live validation

Every form in this application validates against the same rules twice: once as
you type, once when you submit. Both times it is the form request that answers,
so the message on the field and the message after the round trip cannot
disagree.

## One source of rules

Precognition sends the half-filled form to the real route with a header that
says "do not do it, just validate". Laravel runs the form request and stops
before the controller. Nothing is duplicated into TypeScript, which is where
live validation usually rots: the rule changes on the server and the browser
keeps cheerfully accepting the old shape.

`resources/js/pages/user-profile/edit.tsx` shows the pattern — `useForm` from
`laravel-precognition-react-inertia` pointed at the Wayfinder URL, with
`<InputError>` rendering whatever came back.

## The parity gate

A form validating live against a route that lacks
`HandlePrecognitiveRequests` gets **no** validation at all: the request falls
through to the controller and does the thing. That failure is silent, which is
why the gate is the router rather than a list of forms somebody maintains.

`tests/Feature/PrecognitionParityTest.php` walks every registered route, finds
the ones whose action type-hints a `FormRequest`, and requires the middleware on
each — or an entry in the allowlist.

## The allowlist is deliberately hard to widen

`App\Support\PrecognitionAllowlist::shipped()` names the routes that are excused
and why. Two things keep it from becoming an off switch: every entry carries a
written reason (an empty one throws) and the list is capped at
`PrecognitionAllowlist::MAXIMUM` — five. A sixth route cannot be added quietly;
the application refuses to build the list.

## The controls, and the tests that prove them

```bash
bin/prove-control.sh phase11-parity PrecognitionParity 'precognition|middleware'
bin/prove-control.sh phase11-allowlist PrecognitionAllowlist
```

The first removes the middleware from a route group and the parity test goes
red. The second disables the cap and the allowlist test notices.

## Related

- [[domains/ux-primitives]] — how a refused write reads once it is submitted
- [[domains/http-layer]] — the middleware stack these routes sit in
