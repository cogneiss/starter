---
title: Localization
status: current
supersedes: []
code_refs:
    - app/Http/Middleware/SetLocale.php
    - app/Http/Controllers/UserLocaleController.php
    - resources/js/lib/i18n.ts
    - lang/en/ui.php
    - lang/nl/ui.php
    - database/migrations/2026_08_30_110000_add_locale_to_users_table.php
    - tests/Unit/LocaleKeyParityTest.php
    - tests/Feature/Middleware/SetLocaleTest.php
    - tests/Browser/I18nFallbackTest.php
    - tests/Mutations/phase14-locale-key.patch
updated: 2026-09-01
---

# Localization

One set of translation files, read by the server, handed to the page. The client
holds no second copy of any string, so a translation can never be current in PHP
and stale in TypeScript.

`config/app.php` lists what is served — `supported_locales`, currently `en` and
`nl`.

## Which language a request is answered in

`App\Http\Middleware\SetLocale` asks four sources in the order a person would
expect:

1. the locale they chose (`users.locale`),
2. the locale this session was already using,
3. the browser's `Accept-Language`, in its own order of preference,
4. `app.locale`.

Every one is filtered through the supported list. A stored preference for a
locale that has since been dropped, or a header naming anything at all, lands on
the default rather than on a half-translated screen. A header this application
has no words for answers nothing, and the default decides.

Choosing is a write to the user (`user-locale.update`), because a language is a
durable preference and not a comfort setting — see [SETUP.md](../../SETUP.md)
for where each kind of preference lives.

## On the page

`resources/js/lib/i18n.ts` reads the `translations` page prop and nothing else.
`useTranslate()` returns the translator for the current page.

A key nobody has translated yet **renders as itself**. Blank would be worse: an
untranslated button is a button with no words on it, and nothing on the screen
would say which string went missing.

The fallback is deliberately not "show the English". Silently substituting
another language makes a missing translation invisible in review and leaves a
Dutch page with English sentences scattered through it.

## Every locale ships every key

`tests/Unit/LocaleKeyParityTest.php` compares the key sets across locales and
requires a file for every supported one. A key added to `en` and forgotten in
`nl` fails the suite rather than reaching a reader:

```bash
bin/prove-control.sh phase14-locale-key LocaleKeyParity 'key|locale'
```

The patch deletes one Dutch key; the parity test is what notices.

## Related

- [[domains/ux-primitives]] — the copy tables that go through the same files
- [[features/interface]] — the screens these strings appear on
