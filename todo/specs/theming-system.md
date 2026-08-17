# Spec: WordPress-grade theming for the starter kit

## 1. Problem

Kit has token-level dark/light only; no way to build, install, or switch full themes (markup + layout + tokens) developed independently of the platform. Goal: WordPress-grade platform/theme separation — themes side-by-side, coded against a stable typed contract, never touching core.

## 2. Approach

- **Themes as sibling folders** `resources/js/themes/<name>/`, active one picked by `VITE_THEME` env at build time. Default theme = current UI carved out.
- **Vite resolver plugin** (in `vite.config.ts`): alias `@theme/x` → `themes/<active>/x` → `themes/<parent>/x` (from theme's `theme.json`) → `themes/default/x`. Build-time = tree-shaken, type-checked, zero runtime cost. WP child-theme hierarchy, static.
- **Typed contract** `resources/js/theme-contract/`: prop interfaces for every themeable unit (layouts, nav, auth chrome). Pages import via `@theme/*` only; themes implement interfaces. `tsc` = theme validation (stronger than WP).
- **Slots**: minimal named-slot component (`<Slot name="app.sidebar.footer">`) with theme-registered fills from theme entry — WP `add_action` equivalent, no page edits by themes.
- **Tokens ride in theme**: `themes/<name>/theme.css` imported via same resolver (`@theme/theme.css`); tweakcn output drops in.
- **Runtime token variants stay** — existing appearance (dark/light) system untouched; a theme may additionally ship `data-theme` palette variants using the existing `HandleAppearance` cookie pattern.
- **Tooling**: `php artisan theme:check` (tsc + manifest validation), `theme:use {name}` (writes `VITE_THEME` to `.env`, runs build).

**Rejected alternative**: shadcn-registry file overwrite (themes install by copying over `components/ui/*`). Cheap but destructive — no side-by-side themes, no clean switching, no contract, kit updates silently break themes. Registries stay relevant later as pure distribution.

## 3. Changes

**New:**

- `vite-theme-plugin.ts` (repo root or `resources/js/build/`) — ~60-line resolver: reads `VITE_THEME`, parses active `theme.json`, resolves `@theme/*` with parent fallback chain
- `resources/js/themes/default/` — move: `layouts/*` (app-layout, auth-layout + variants, settings), chrome components (`app-sidebar`, `app-header`, `app-shell`, `app-content`, `app-sidebar-header`, `nav-*`, `user-menu-content`, `app-logo*`, `breadcrumbs`), `theme.css` (token blocks extracted from `app.css` `:root`/`.dark`)
- `resources/js/themes/default/theme.json` — `{ name, parent: null, version, contract: "1.0" }`
- `resources/js/theme-contract/index.ts` — interfaces: `AppLayoutProps` (exists in `@/types` — move/re-export), `AuthLayoutProps`, `NavItem` (exists in `types/navigation.ts`), slot name union
- `resources/js/components/slot.tsx` + `theme-contract/slots.ts` — slot component + fill registry
- `app/Console/Commands/ThemeCheck.php`, `ThemeUse.php` (via `make:command`); logic in `app/Actions/` per kit convention
- Example theme `resources/js/themes/minimal/` overriding one layout + tokens — proves fallback

**Modified:**

- `vite.config.ts` — register resolver plugin
- `tsconfig.json` — `"@theme/*": ["./resources/js/themes/default/*"]` (types resolve against default = contract source of truth; active-theme overrides remain type-safe via `theme:check` second pass)
- `resources/css/app.css` — token blocks out, `@theme` mapping stays; import theme css
- All `resources/js/pages/**` + `app.tsx`/`ssr.tsx` — imports `@/layouts/*` → `@theme/layouts/*`; chrome component imports likewise (mechanical sweep)
- `vite.config.ts` fmt `ignorePatterns` + eslint scope for `themes/*`
- `.env.example` — `VITE_THEME=default`

**Untouched:** `components/ui/*` (shadcn primitives are platform, not theme — themes restyle via tokens or override individual primitives through the same resolver if they choose), `use-appearance` system, wayfinder/actions/routes, Fortify backend.

## 4. Edge cases & risks

- **SSR**: `ssr.tsx` bundle must resolve identical theme — plugin applies to both builds (same config object; verify)
- **HMR**: resolver must register theme dirs for watch; alias-based resolution keeps HMR working
- **Circular parent chains** in `theme.json` — resolver must detect, error at build start
- **`import.meta.glob` for pages** doesn't go through resolver — pages stay platform-owned, so fine; document that themes cannot add pages in v1 (WP templates-for-CPTs analog deferred)
- **tsconfig static path vs dynamic active theme**: editor intellisense always shows default theme's types. Acceptable — contract identical; `theme:check` runs tsc with active-theme paths for truth
- **Missing file in all chain levels** — build fails loudly (good), but error must name the `@theme/x` specifier
- **vite-plus lint/fmt** pipeline may need theme dirs whitelisted; `sortTailwindcss.stylesheet` points at `app.css` — token move must keep it valid
- **Test suite**: browser tests render default theme — unaffected; but 100% type-coverage gate (`--exactly=100.0` coverage, type-coverage min 100) means moved files must keep annotations intact

## 5. Test plan

1. **Build matrix**: `VITE_THEME=default bun run build` and `VITE_THEME=minimal bun run build` both succeed; grep minimal bundle for its override marker class, default bundle for absence (proves resolution + tree-shaking)
2. **Fallback**: minimal theme lacks `auth-layout` — build resolves it from default (assert via build success + rendered output)
3. **Pest browser test**: welcome + dashboard + one auth page render under default theme (existing tests already cover; keep green)
4. **New browser test**: build with example theme, assert overridden layout marker visible
5. **`theme:check`**: Pest feature test — valid manifest passes; broken contract (bad prop type in fixture theme) fails with named error; circular parent fails
6. **Full gate**: `composer test` (lint, types, type-coverage 100, unit coverage 100, browser) green on default theme
7. **SSR**: `bun run build` ssr bundle + existing SSR smoke path renders

## 6. Phasing

- **P1** — resolver + default-theme extraction + page import sweep (kit works identically, all tests green)
- **P2** — contract extraction + example theme + `theme:check` / `theme:use`
- **P3** — slots

Each phase independently shippable.
