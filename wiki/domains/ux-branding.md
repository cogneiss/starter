---
title: Per-organization branding
status: current
supersedes: []
code_refs:
    - app/Support/BrandPalette.php
    - app/Http/Middleware/HandleBrand.php
    - app/Console/Commands/BrandPreviewCommand.php
    - database/migrations/2026_08_29_171206_add_brand_colors_to_organizations_table.php
    - tests/Unit/Support/BrandPaletteContrastTest.php
    - tests/Unit/Support/BrandPaletteFallbackTest.php
    - tests/Datasets/BrandPairs.php
    - tests/Feature/Middleware/BrandInjectionTest.php
    - tests/Mutations/phase9-contrast.patch
updated: 2026-08-31
---

# Per-organization branding

An organization stores two hex colours. Everything else — the light palette, the
dark palette, hover and border shades, the foreground colour that sits on each
of them — is derived, because a tenant choosing eleven colours is a tenant
choosing an unreadable interface.

## The derivation

`App\Support\BrandPalette::from()` takes the two hexes and emits both modes as
CSS custom properties. Lightness is the only thing it moves, and only within a
window: a brand that arrives too dark to carry white text is lifted until it
does, and a brand that cannot get there inside the window falls back to the
neutral ramp rather than to something that is no longer the brand.

Hue and chroma are left alone. A palette that quietly recolours a brand is worse
than one that declines to use it.

## Contrast is measured, not assumed

`BrandPalette::PAIRS` names every foreground/background pair the interface can
produce, with the ratio each one has to clear — 4.5:1 for text, 3:1 for borders
and large UI. `tests/Unit/Support/BrandPaletteContrastTest.php` walks
`tests/Datasets/BrandPairs.php` and measures the emitted tokens rather than
trusting the code that produced them, so a change to the derivation that quietly
darkens a hover state fails the suite instead of shipping.

The ratios are measured **after** the OKLCH values are clipped into sRGB. A
colour that is legible in the wide space and not on the screen is a colour
nobody can read.

## Where the palette is applied

`App\Http\Middleware\HandleBrand` shares the tokens into the root Blade template,
not as an Inertia prop. The stylesheet is correct in the first paint, so a
branded tenant never sees the default colours repaint into their own.

Absent colours are the common case: the middleware falls back to
`BrandPalette::DEFAULT_PRIMARY` and `DEFAULT_ACCENT`, so an organization that
never opened the setting looks deliberate.

## Checking a brand before turning it on

```bash
php artisan brand:preview '#3366FF' '#FF9900'
```

Prints the derived palette for both modes with the measured ratio of every pair.

## The control, and the test that proves it

```bash
bin/prove-control.sh phase9-contrast BrandPaletteContrast
```

The patch disables the lightness correction; the contrast test is what notices.

## Related

- [[domains/ux-motion-and-a11y]] — the rest of the accessibility gates
- [[domains/ux-onboarding]] — where a tenant is asked for its colours
