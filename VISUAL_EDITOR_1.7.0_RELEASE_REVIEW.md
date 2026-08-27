# Visual Editor 1.7.0 Release Review — `release/1.7` vs `main`

**Date:** 2026-08-27
**Scope:** Full diff `git diff main...release/1.7` — 107 commits, 312 files, ~34.9k insertions. Major features: Font Library (providers, install pipeline, REST API, modal UI, fonts.css generator), Dynamic Content tab in the inline link popover, inline-icon format, photo-grid wrapper port to React/Vue renderers, shared CSS value whitelist (#720/#721), composed-view work, theme font-family in the post-editor canvas.
**Method:** Six parallel deep-review passes (PHP security, JS security, PHP correctness/perf, renderer parity, style/docs/release readiness, test verification), findings verified against source. Both test suites are green (2,016 PHP tests / 2,956 JS tests; both builds pass).

**Overall verdict:** The branch is in very good shape. The Font Library backend is unusually well hardened (SSRF allowlists, bounded reads, magic-byte gating, path-slugging, atomic writes, double-gated authz — no critical/high/medium security findings). The blockers are one JS renderer crash (H1), the release-prep mechanics (R1–R6), and a handful of medium correctness items. Everything below is written so a fix-up agent can execute it item by item.

**Conventions for the fixer:**
- Do NOT run Pint or any formatter repo-wide; this package has no lint tooling. Match surrounding style by hand (tabs, `if ( $condition ) {`, Yoda, single quotes, aligned `=>`).
- After each fix, run the narrowest relevant tests; before finishing, run `php -d memory_limit=1G vendor/bin/pest`, `npm test`, `npm run build:lib`, `npm run build`, and `npm run verify:parity`.
- React and Vue renderer support files are intentionally code-identical (comment-only diffs). Every fix to `packages/visual-editor-renderer-react/src/support/*` or `src/blocks/core/*` MUST be mirrored byte-for-byte (code, not comments) in the Vue package, and vice versa.

---

## H — High severity (fix before release)

### H1. Uncaught `RangeError` from `xxh3_64_hex` crashes the entire React/Vue render for column width maps > 240 bytes ✅ verified in code
- **Files:** `packages/visual-editor-renderer-react/src/support/columnWidth.ts:57-64` and the Vue mirror `packages/visual-editor-renderer-vue/src/support/columnWidth.ts`; thrower at `packages/visual-editor-renderer-{react,vue}/src/support/xxh3.ts:239`.
- **Issue:** `columnWidthScope()` wraps only `phpJsonEncode()` in try/catch; the `xxh3_64_hex( json )` call is outside it, and the XXH3 port throws `RangeError` for any input whose UTF-8 encoding exceeds 240 bytes. A `core/column` with several breakpoint overrides of long `calc(...)` widths (e.g. five × `calc(100% - var(--wp--style--block-gap, 0.5em) * 0.5)` ≈ 308 bytes of JSON) throws, and the exception propagates through `stampColumnWidthScopes()` → `useMemo` in `BlockTree.tsx:178` (Vue: `computed` in `BlockTree.ts:203`), killing the whole page render (SSR 500 / client white-screen). Blade's `hash('xxh3', …)` handles any length, so this is both a crash and a parity divergence. Reachable with legitimate editor data.
- **Fix (preferred):** implement the >240-byte long-input path of XXH3-64 in `xxh3.ts` so the token matches Blade for all inputs. **Fix (minimum):** move the hash call inside the try block and return `null` on `RangeError` (drops the scope class), then document the divergence and add a long-width parity fixture. Add a unit test for a >240-byte width map in both renderers either way.

---

## R — Release-prep blockers (mechanics; required before tagging)

### R1. Version fields still say 1.6.0
- **Files:** `composer.json:4` (`"version": "1.6.0"`), `package.json:3` (`"version": "1.6.0"`), `docs/home.md:182` (footer says "covers visual-editor v1.6.0" while `docs/fonts.md:3` and `docs/font-providers.md:3` are stamped v1.7.0).
- **Fix:** bump all three to 1.7.0.

### R2. CHANGELOG `[Unreleased]` is materially incomplete for 1.7
- **File:** `CHANGELOG.md` lines 7–77.
- **Issue:** Only covers the Font Library, the `ap.cmsFramework.comments.form.action` rename, and one renderer-blade test fix. Missing (all confirmed via `git log main..release/1.7`):
  - **PHP requirement bump `^8.2` → `^8.3`** (`composer.json`, CI matrix, `CONTRIBUTING.md`) — call out prominently; it's a hard host requirement change.
  - Shared CSS-value whitelist on renderer `<style>` emission (#720/#721) — a **security** change; note it as such.
  - Inline-icon RichText format (#717/#718).
  - Dynamic Content tab in the inline link popover (#662/#746).
  - Photo-grid wrapper port to React/Vue renderers (#714).
  - Column responsive-width port (#712).
  - Theme global font-family applied in the post-editor canvas (`aa73c5cc`).
- **Fix:** add entries under the right Added/Changed/Fixed/Security headings; retitle to `## [1.7.0] - <date>` at release time.

### R3. `npm run verify:parity` fails — `renderer-parity.json` missing the blade-only html blocks
- **File:** `packages/renderer-parity.json`.
- **Issue:** Blade reports `extra: artisanpack/html, core/html` (added by #708, never added to the manifest). React/Vue register all 152 blocks fine. CI only runs `upstream-diff`, so this never failed a pipeline.
- **Fix:** add `core/html` and `artisanpack/html` to the manifest as intentionally blade-only (JS falls back to `DynamicBlock` server rendering), or port them. One-line manifest fix.

### R4. Resolve the v1.6.0 tag ambiguity
- **Issue:** `CHANGELOG.md:77` dates `[1.6.0] - 2026-08-07` as released, but no `v1.6.0` tag exists in the clone (tags stop at `v1.5.5`) and `main` still carries 1.5.5.
- **Fix (decision for Jacob, not the fix agent):** either the tag wasn't pushed (push it), or 1.6.0 never shipped and 1.7.0 is the first release carrying both waves — in which case fold the 1.6.0 changelog section into 1.7.0 or annotate it.

### R5. Renderer subpackage version decision
- **Files:** `packages/visual-editor-renderer-react/package.json`, `packages/visual-editor-renderer-vue/package.json` (both 1.2.0, bumped in the 1.6.0 prep).
- **Issue:** New renderer features (photo grid, responsive width, CSS whitelist, xxh3/sha1) landed after that bump. If 1.2.0 was already published to npm, these need 1.3.0; if not, 1.2.0 stands.
- **Fix (decision):** check the npm registry for the published version and bump if needed.

### R6. Pest suite OOMs at the default 128M CLI memory limit
- **Issue:** Bare `./vendor/bin/pest` dies with `Allowed memory size of 134217728 bytes exhausted` in PHPUnit's `CachingParser` partway through `FontLibraryControllerTest`; fully green with `-d memory_limit=1G`.
- **Fix:** add `<ini name="memory_limit" value="1G"/>` (or 512M) to `phpunit.xml`'s `<php>` block, or note the requirement in CONTRIBUTING.

---

## M — Medium severity (should fix before release)

### Editor / site-editor JS

### M1. Post-editor canvas color tokens read `/global-styles/base` and miss site-editor-saved user customizations
- **Files:** `resources/js/visual-editor/editor/editor-canvas.tsx` (~line 155, `useThemeGlobalStylesSettings(apiBase)` → `canvasColorTokenStyle(themeBase?.styles)`); `resources/js/visual-editor/editor/canvas-color-tokens.ts` (`buildCanvasColorTokenCss`, line 292).
- **Issue:** `GlobalStylesController::base()` serves theme defaults only; applied user styles exist only in the `/css` payload. The #695 fix derives `--ap-editor-canvas-bg/-fg/-heading-fg` from `base.styles`. If the user changes the palette in the site-editor Styles panel (DB override), the `/css` payload carries e.g. a dark background scoped `.editor-styles-wrapper` (specificity 0,1,0), but `canvas-theme-tokens.css` paints via `body.editor-styles-wrapper { background-color: var(--ap-editor-canvas-bg, #ffffff) }` (0,1,1) which wins — the canvas stays theme-default light, and the exact #695 dark-heading bug returns for customized palettes. This is a known failure mode for this codebase (base vs css data sources).
- **Fix:** derive the tokens from merged styles — add a merged-styles field to the base endpoint (or a small `/global-styles/applied` JSON surface) and read that; alternatively lower the painting rule's specificity to `.editor-styles-wrapper` so the scoped `/css` declarations win. Add a test with a DB-overridden palette.

### M2. Theme-CSS cache goes stale when a font is installed while no canvas is mounted
- **File:** `resources/js/visual-editor/site-editor/use-theme-global-styles-css.ts:156-164`.
- **Issue:** The `subscribeFontsChanged` invalidation lives in a `useEffect` of the hook, so it only runs while a consumer (a canvas) is mounted. The Styles section — where the Font Library button lives (#739) — mounts none. Flow: visit Templates (cache resolves) → Styles → install a font → back to Templates: the lazy `useState` initializer returns the stale entry synchronously; no `@font-face` for the new font until reload.
- **Fix:** move invalidation to module scope — `subscribeFontsChanged( () => cache.clear() )` once at import time (keep the per-hook token bump for re-rendering mounted consumers). Add a test for the away-flow.

### M3. `scopeGlobalStylesCss` only rewrites `:root` — `body`/`html` rules leak into the host document and `:root.dark` variants stop matching
- **Files:** `resources/js/visual-editor/site-editor/scope-global-styles-css.ts` (whole rewriter), consumed at `canvas-theme-styles.tsx:46-48`.
- **Issue:** The payload includes the theme's hand-authored `themes/{slug}/style.css`, which routinely contains `body { … }` / `html { … }` / bare element selectors; since the site editor mounts inline (#418) these style the editor shell unscoped — the same bug class #679 fixed for `:root`. Separately, `:root.dark` / `:root[data-theme="dark"]` become `.editor-styles-wrapper.dark`, but themes toggle that class on `<html>`, so dark-mode overrides silently stop applying inside the canvas.
- **Fix:** scope whole rule selectors: prefix every top-level selector with `.editor-styles-wrapper`, mapping `:root|html|body` to the wrapper itself; rewrite `:root<suffix>` to a `:where(html<suffix>) .editor-styles-wrapper` form. At minimum, correct the docblock's "strict narrowing" claim and document the limitation.

### M4. Composed-view fallback toast silences a *different* failure when the copy is identical
- **File:** `resources/js/visual-editor/editor/composed-view/use-fallback-toast.ts:71-77` (latch keyed on `` `${tone}:${message}` ``; messages at `fallback.ts:96,130`).
- **Issue:** `error`-tone and `missing/empty` messages are template-independent. Template A's fetch errors → toast; author picks template B without leaving composed mode; B errors too → byte-identical key → silenced. The latch clears only on `status === 'ok'` (line 61). The doc block (lines 11–14) promises the opposite. The existing "different failure" test (`__tests__/use-fallback-toast.test.tsx:127`) only covers two `unknown-slug` misses.
- **Fix:** fold the requested template slug (thread it through `UseComposedFallbackToastOptions`) or the hook's cache key into the latch key. Add a test: two consecutive error-tone failures for different templates both announce.

### M5. One-frame render of a stale template's chrome after changing template while composed view is off
- **File:** `resources/js/visual-editor/editor/composed-view/use-applied-template.ts:141-161`.
- **Issue:** `state` carries no record of which cache key it belongs to. With `enabled` false and the key changed, the effect early-returns leaving the previous template's `{status:'ok'}`. On re-toggle, one committed frame renders template A's header/footer and a notice naming A before `run()` fires (flash-of-A → blank → B on a cache miss).
- **Fix:** store `{ key, state }` and treat a key mismatch as `loading` at read time (or reset to loading in the effect before the `enabled` gate). Add a test.

### Font Library PHP

### M6. Synchronous install pipeline can exceed PHP max_execution_time inside a web request
- **Files:** `src/Fonts/Services/FontInstaller.php:227-267`, `src/Http/Controllers/Fonts/FontLibraryController.php:320-349`, `src/Http/Requests/Fonts/InstallFontRequest.php` (`faces` max:50).
- **Issue:** `store()` runs `install()` inline; each face costs up to 2 sequential HTTP fetches (10s timeout each) × up to 50 faces. On a PHP fatal timeout neither the `catch ( Throwable )` cleanup nor the transaction runs — face files written so far stay on disk with no DB rows (self-healing on retry since paths are deterministic, but the modal 504s).
- **Fix:** lower the `faces` cap (e.g. `max:18`), and/or enforce a total wall-clock budget inside the fetch loop that aborts cleanly (throw `FontInstallException`) well before PHP's hard timeout. (A queued-install mode for large selections is the longer-term answer; the `fonts.regenerate.queued` config hook already exists.)

### M7. Cross-server race: install serialization depends on a shared, lock-capable cache store
- **File:** `src/Fonts/Services/FontInstaller.php:126-133, 455`.
- **Issue:** `Cache::lock()` on a non-shared store (`file` cache, multi-server) lets two servers install the same family concurrently; the loser's rollback deletes files the winner's committed `FontFace` rows reference → 404ing `@font-face` in fonts.css.
- **Fix:** in the rollback path, re-check `Font::where( 'provider', … )->where( 'slug', … )->exists()` before deleting files (skip file deletion if another install committed); and document the shared-lock-store requirement in `docs/fonts.md` + config comment.

### M8. Preview-face endpoint can bloat the shared cache store by gigabytes
- **File:** `src/Http/Controllers/Fonts/FontLibraryController.php:243-266`.
- **Issue:** Each previewed face is cached base64-encoded (up to ~20MB per entry at the 15MB fetch ceiling) for 24h, keyed per provider/slug/weight/style. Any authenticated read-only user at 120 req/min can enumerate the ~1,800-family Google catalog, pushing multi-GB into Redis/database cache and proxying every face through the app. Real preview WOFF2s are tens of KB.
- **Fix:** refuse to cache (and optionally to serve) preview bodies over a small ceiling (e.g. 2MB — even 512KB is generous); tighten the preview-face throttle below the general 120/min read limit.

### M9. Bunny "variable" fonts persist `is_variable = true` with empty axes; documented parser pass never runs
- **Files:** `src/Fonts/Providers/BunnyFontsProvider.php:19-26` (docblock) vs `src/Fonts/Services/FontInstaller.php:265` (`writeAndPersist()`).
- **Issue:** The docblock says axis metadata is recovered by the variable-font parser at install time, but only `installUpload()` invokes `VariableFontMetadataParser` — the catalog path never does. Bunny variable families install with `is_variable = true`, `axes = []`, so the typography picker gets nothing.
- **Fix:** run `$this->metadataParser->parse( $bytes )` per face in `writeAndPersist()` and persist the result (harmless no-op for static faces), then fix the docblock; or correct the docblock and stop persisting a flag the data can't back. Add a test either way.

### M10. Untranslated exception messages surfaced verbatim in editor-facing JSON
- **Files:** `src/Http/Controllers/Fonts/FontLibraryController.php:131,344,370` (`'message' => $e->getMessage()`); sources in `FontInstaller.php` (~96), `GoogleFontsProvider.php:198-242,496,624,634`, `BunnyFontsProvider.php:204-248,479,607,617`, `CustomUploadProvider.php` (~113).
- **Issue:** These strings render in the Font Library modal, while the same controller wraps its *other* user-facing strings in `__()` (lines 119, 331) — inconsistent i18n in new code, violating the house translatable-strings rule.
- **Fix:** wrap the actionable installer/provider exception messages in `__()` at the throw site (keep interpolation via `:param` placeholders where sprintf is used today), or map exception classes to translated generic messages in the controller and log the raw text.

### Renderer parity (all fixes must be mirrored React ↔ Vue)

### M11. Parity goldens strip all `<style data-ve-*>` blocks — the new whitelist/photo-grid CSS bodies are never compared
- **Files:** `packages/renderer-markup-parity/tests/blade-parity.test.ts:80-82` (`stripRendererStyleTags`); PHP twin `packages/visual-editor-renderer-blade/tests/Feature/RendererMarkupParityTest.php:112-114`.
- **Issue:** The strip regex removes `data-ve-column-width` and `data-ve-photo-grid` blocks too, so two renderers can emit different rule bodies under the same hashed class token and every golden passes. The byte-identical contract is unenforced for exactly the CSS #712/#714/#720 emit. This is what masks M12/L-tier divergences below.
- **Fix:** capture `data-ve-column-width` / `data-ve-photo-grid` style contents into the canonical form (normalized like `style=""` attributes) in BOTH harnesses; keep stripping only the baseline/global-styles tags. Then regenerate goldens (`composer test:update-markup-goldens`) and fix whatever divergences surface (expect M12/L14–L17).

### M12. Boolean width values: PHP `(string)` cast vs JS `String()` diverge
- **Files:** `packages/visual-editor-renderer-{react,vue}/src/support/columnWidth.ts:287` (`normalizeBasis`), `src/blocks/core/layout.tsx:149` inline path, vs `packages/visual-editor-renderer-blade/resources/views/blocks/core/column.blade.php:84-86,157`.
- **Issue:** PHP `(string) true === '1'` / `false === ''`; JS `String(true) === 'true'` / `'false'`. `responsive.width.md = false`: Blade skips the rule; JS emits `flex-basis:false!important` under the same `ve-w-` token (masked by M11) — and if it's the only entry, Blade drops the wrapper class while JS keeps it (golden-visible, no fixture). `width = true`: Blade emits inline `style="flex-basis: 1;"`, JS emits none.
- **Fix:** reject non-string/non-number width values on both sides explicitly (Blade: `continue` on non-scalar; JS: return `null` for booleans/objects in `normalizeBasis` and skip in the inline path). Add fixtures for `false` and `true` widths.

### M13. Native `trim()` vs PHP `trim()` in the layout-class path
- **Files:** `packages/visual-editor-renderer-{react,vue}/src/support/attributes.ts:112` (`layoutClass`), `src/blocks/core/layout.tsx:44` (`storedLayoutType`), vs `packages/visual-editor-renderer-blade/src/Support/LayoutSupport.php:60-62`.
- **Issue:** JS `trim()` strips U+00A0/U+FEFF/`\f`; PHP `trim()` strips only `" \t\n\r\0\x0B"`. `layout.type = "flex "` → Blade renders `is-layout-flow`, JS renders `is-layout-flex` (and the #595 ap-flex override gate diverges the same way). `photoGrid.ts:196-198` already has the correct `phpTrim()` for exactly this reason.
- **Fix:** export `phpTrim` from a shared support module and use it in `layoutClass()` and the group override in both JS renderers. Add a whitespace-suffixed `layout.type` parity fixture.

### M14. Group #595 override gate diverges for non-string `layout.type`
- **Files:** `packages/visual-editor-renderer-{react,vue}/src/blocks/core/layout.tsx:44-49` (Vue `layout.ts:57-62`) vs `packages/visual-editor-renderer-blade/resources/views/blocks/core/group.blade.php:11-13,30-33`.
- **Issue:** Blade treats non-string `layout.type` as `''` (override fires → `is-layout-flex`); JS `attrString()` coerces `5`/`true` to `'5'`/`'true'` (override skipped → `is-layout-flow`). Golden-visible for malformed/hostile trees; no fixture.
- **Fix:** in the JS gate, read the raw value: `typeof type === 'string' ? phpTrim( type ) : ''`. Add a fixture with `layout.type = 5`.

---

## L — Low severity (fix if time allows; none block the release)

### Font Library PHP
- **L1. Cache headers on authenticated preview routes.** `src/Http/Controllers/Fonts/FontLibraryController.php:222-225,262-265` send `Cache-Control: public, max-age=86400` behind `auth` middleware. Change `public` → `private`.
- **L2. `bulkUninstall` accepts an unbounded `ids` array.** `FontLibraryController.php:388-391` — add `'max:100'` and `'distinct'` to the `ids` rule (each id costs a query + a 15s lock block in `FontInstaller::bulkUninstall()`).
- **L3. Upload validation checks only a 4-byte signature.** `FontInstaller::isFontSignature()` — the remaining ≤5MB is attacker-controlled bytes hosted same-origin (privileged uploaders only; extension is server-derived so no execution risk). Optional hardening: run `VariableFontMetadataParser::tables()` (or a table-directory sanity check) as a validity gate before persisting.
- **L4. `regenerate()` runs outside every lock** (`FontInstaller.php:130-137,560-561,624`) — an install/uninstall interleave can leave fonts.css pointing at just-deleted files until the next mutation. Wrap `generate()` in a shared `Cache::lock('ve.fonts.regenerate')` (or regenerate inside the family lock).
- **L5. Format change orphans the previous face file.** `FontInstaller.php:248-296,405-449` — `updateOrCreate` keyed on weight+style; when `path` changes (`.woff2` → `.ttf`), delete the prior path from the retrieved model.
- **L6. `uninstall()` snapshots face files before taking the lock.** `FontInstaller.php:544-561` — move the relation load inside the lock closure (`$font->load( 'faces' )` after `block()`).
- **L7. Catalog re-unserialized from cache on every call.** `GoogleFontsProvider.php:265-270` (same in Bunny) — add a `protected ?array $catalog = null` per-instance memo; the providers are effectively singletons via the registry. (An 18-face install pays the multi-MB unserialize ~19×.)
- **L8. `FontsCssGenerator::generate()` move semantics assume local POSIX.** `src/Fonts/Services/FontsCssGenerator.php:114-121` — on Windows, Flysystem-local `rename()` fails when the destination exists (bundle permanently stale after first write). Add a delete-then-move fallback when `move()` throws; document the S3 caveat (non-atomic move, cross-origin `Storage::url()`).
- **L9. Live-endpoint smoke test needed.** `GoogleFontsProvider.php:476-479`, `BunnyFontsProvider.php:459-462` — `http_build_query` percent-encodes `:`/`,`/`@` in the `family` spec and every test stubs HTTP; nothing proves the live CSS2/Bunny endpoints accept the encoded form. **Manual step for Jacob before tagging:** install one Google + one Bunny family against the real endpoints.
- **L10. Upgrade-notes sentence:** the API's auth rests entirely on `artisanpack.visual-editor.api.middleware` (default `['api', 'auth']`, `src/VisualEditorServiceProvider.php:1345-1353`). Hosts overriding it without `auth` expose the catalog/preview routes as an unauthenticated fetch amplifier. One sentence in `docs/fonts.md` / release notes.

### JS frontend
- **L11. Provider `preview_url` injected as a stylesheet with no origin check.** `resources/js/visual-editor/fonts/api-client.ts:288` → `font-preview.tsx:147-161`. First-party path is safe today, but a family without a `slug` passes through `withPreviewUrls()` undecorated, and third-party providers registered via `ap.visualEditor.registerFontSources` can return arbitrary absolute URLs the modal loads as CSS — violating the GDPR "your browser never contacts the provider's CDN" notice. Fix: in `catalogPreviewUrl`, accept only same-origin paths (`startsWith('/') && !startsWith('//')`), return `undefined` otherwise.
- **L12. `normalizeLinkUrl` passes `javascript:` through.** `resources/js/visual-editor/formats/dynamic-link/value.ts:33,52-64`. Matches stock Gutenberg, but the server side (`DynamicContentSource::SAFE_URL_SCHEMES`, Blade `UrlSanitizer`) rejects it. Fix: reject `javascript:`/`data:`/`vbscript:` schemes in `normalizeLinkUrl` (return `''`).
- **L13. Raw NUL byte makes `use-applied-template.ts` binary to git.** `resources/js/visual-editor/editor/composed-view/use-applied-template.ts:53` — `const NO_TEMPLATE = '␀unset'` contains literal U+0000, so the 200-line hook has no reviewable diff anywhere. Fix: `' unset'` (identical runtime value).
- **L14. Inline-icon `color` override skips the CSS value whitelist.** `resources/js/visual-editor/formats/inline-icon/settings.ts:75-88` — free-text `ColorPalette` custom value interpolated into the span's `style` (`red;position:fixed;inset:0` persists extra declarations; attribute-escaped so no breakout). Fix: validate with a color-shaped whitelist (e.g. `/^[#\w(),.%\s-]+$/` per `canvas-color-tokens.ts`'s `ALLOWED_VALUE`, minus `/`); confirm the PHP `InlineIconContentHydrator` re-sanitizes `style` at render.
- **L15. Duplicate-filename React keys in the Upload tab.** `resources/js/visual-editor/fonts/font-library-modal.tsx:560-564` — `key={file.name}`; use the index.

### Renderer parity lows (mirror React ↔ Vue; most are masked by M11 until the harness is fixed)
- **L16. `isNumeric()` accepts JS-only formats.** `columnWidth.ts:327-339` — `'0x1A'`/`'Infinity'` pass; PHP rejects. Tighten the string branch to a decimal regex (`/^[+-]?(\d+(\.\d+)?|\.\d+)([eE][+-]?\d+)?$/` after trim) and reject non-finite results.
- **L17. `safeCssValue` empty-check diverges on `"\f"`.** `packages/visual-editor-renderer-{react,vue}/src/support/cssValue.ts:41` vs `BlockSupports.php:1440` — JS `trim()` treats `\f` as whitespace, PHP doesn't. Replace `value.trim() === ''` with a PHP-trim-equivalent check (`/^[ \t\n\r\0\x0B]*$/`).
- **L18. `isNonEmptyWidth()` promotes objects/arrays PHP `empty()` rejects.** `columnWidth.ts:261-267` vs `column.blade.php:130` — `width: {}` plus a responsive override hashes different JSON → different `ve-w-` tokens. Make `isNonEmptyWidth` return false for non-scalars.
- **L19. `clampColumns` exponent divergence.** `query.tsx:41-50` + `attributes.ts:43-57` vs `post-template.blade.php:33-43` — `'5e3'`: PHP `(int)` → 5000 → clamp 12; JS `parseInt` → 5. Align (anchor Blade's regex to full-match digits, or JS parse via `Number()` + trunc).
- **L20. Buttons `justifyContent` enum-whitelisted in Blade only.** `buttons.blade.php:11-12` vs `layout.tsx:179` — JS emits `is-content-justification-${justify}` for any string (minor class injection + divergence). Port the seven-value enum + `left` fallback into `ButtonsBlock` in both JS renderers; add a hostile-justify fixture.
- **L21. Pre-existing inline `flex-basis` divergences.** `layout.tsx:146-161` vs `column.blade.php:84-86` — `width: 0`/`"0"` (Blade skips, JS emits `flex-basis:0%`) and `"60.0"` (Blade → `60%`, JS raw). Mirror PHP `empty()` (reuse `isNonEmptyWidth`) and normalize via shared `normalizeBasis`.
- **L22. Fixture gaps** (`packages/renderer-markup-parity/fixtures.json`): add hostile photo-grid (invalid `aspectRatio` `"16/0"`, hostile `objectPosition`, non-object `photoGrid`, whitespace edges), the `//`/`/*` digraph branch of the whitelist, the flex arbitrary-value guard (`flex-serializer.ts:346-365` / `FlexSupport.php:350-370`), and one `core/html` fixture + documented-divergence entry.

### Composed-view / site-editor lows
- **L23. Cache key uses untrimmed `template`.** `composed-view/api.ts:82` vs `use-applied-template.ts:70` — trim once in the hook before both uses.
- **L24. Hook accepts and ignores a caller `signal`.** `use-applied-template.ts:41` — change the options type to `Omit<AppliedTemplateConfig, 'signal'>`.
- **L25. Superseded-key responses dropped, not cached.** `use-applied-template.ts:107-109` — move `cacheRef.current.set(key, next)` above the staleness check (cache, then skip `setState`).
- **L26. Second `post-content` slot renders duplicate content in footer chrome.** `composed-view/split.ts:110-112` — deliberate first-slot-only split, but later slots render the post's content a second time read-only. If unwanted, filter `CONTENT_SLOT_NAMES` members out of the `after` list (top level + recursive walk).
- **L27. `url()` skipper edge cases in `scope-global-styles-css.ts:90-104`** — guard the `url(` match against a preceding ident char, honor `\)` escapes in unquoted URLs, match `:root` case-insensitively.
- **L28. Deep-link Back-button dead URL.** `use-deep-link.ts:91-220` + `use-site-editor-routing.ts:192-194` — after in-SPA navigation during an in-flight slug lookup, Back restores the `?entity=…` URL with no re-resolution. Re-parse `location.search` at popstate time. (Also: StrictMode double-fetch — cosmetic.)
- **L29. `resolveSpaRouteBase` rejects a root mount.** `use-site-editor-routing.ts:78-95` + `deep-link.ts:108-116` — `'/'` normalizes to `''`, fails validation, falls back to `/visual-editor/site`. Special-case `'/'` → `''` if root mounting should work.
- **L30. Pattern-usage count trusts a single 100-row page.** `site-editor/patterns/use-pattern-usage.ts:222-229` — paginate the `listEntities` walk (or hard-fail past 100) to match the file's new fail-loud stance; translate the fallback error string.

### Style / docs nits
- **L31. `=>` alignment breaks:** `src/Http/Requests/Fonts/InstallFontRequest.php:62-63`, `src/Http/Requests/Fonts/UploadFontRequest.php:135`, `src/Support/HookAliases.php:69` (one space short). Re-pad to the column.
- **L32. British spellings in new docblocks:** `AuthorizesFontManagement.php:4,8,34,44` ("authorisation"/"unauthorised"/"Authorise"), `UploadFontRequest.php:13-15`, `InlineIconContentHydrator.php:~64` ("colour"). Normalize to American.
- **L33. `config/visual-editor.php` `fonts.capability` comment (~652)** says only `hasCapability()` is probed; `FontPolicy::userHasCapability()` probes `hasCapability()`/`hasPermissionTo()`/`hasPermission()`. Reword.
- **L34. Numeric-separator inconsistency:** config `upload` block uses `5120`/`25600`; `UploadFontRequest.php:45,54` uses `5_120`/`25_600` (and the config's own `max_bytes` is `15_728_640`). Use separators in the config.
- **L35. `formats/dynamic-link/` internally style-mixed:** `edit.tsx` is WP-spaced; `value.ts:54-60` and `register.ts:42,55` are compact. Align `value.ts`/`register.ts` to WP style (matches sibling `inline-icon/`).
- **L36. `ChromeBlocks.tsx` PascalCase filename** in an all-kebab-case `editor/` tree — rename to `chrome-blocks.tsx` (update imports).
- **L37. Docs:** `docs/site-editor.md` Fonts REST table missing `GET /fonts/sources/{provider}/preview/{slug}/{weight}/{style}` (`routes/api.php:352`); `docs/fonts.md` config table missing `fonts.upload.max_total_kilobytes` (25600). Add both rows.
- **L38. Blade sub-package standalone `composer test` is broken by design** (no local Pest bootstrap; tests run via the root `RendererBlade` suite). Delete the misleading script or add a local bootstrap. Also `rm -rf vendor composer.lock` in `packages/visual-editor-renderer-blade/` if a dangling symlinked vendor is present from review verification.
- **L39. Constrained-group containment CSS is a one-way divergence not recorded in `knownDivergences`.** `packages/visual-editor-renderer-{react,vue}/src/support/layoutBaselineCss.ts:39-60` — the `.wp-block-group-is-layout-constrained` containment rules have no Blade twin (Blade gates them on theme.json sizes in `ThemeJsonTokensCompiler`) and auto-margin behavior differs when no content size is configured. Documented in the code comment but `fixtures.json`'s `knownDivergences` list is empty. Fix: add an entry there (and a line in the release notes / renderers doc).

---

## T — Test coverage gaps (add tests; no code defect implied)

- **T1.** `formats/dynamic-link/edit.tsx`, `dynamic-content/dynamic-link-picker.tsx`, and `formats/dynamic-link/register.ts` — the interactive popover-tab UI has zero tests (helpers/value logic are covered).
- **T2.** `formats/inline-icon/edit.tsx` and `fetch-svg.ts` — same UI-layer pattern, untested.
- **T3.** `src/Http/Requests/Fonts/InstallFontRequest.php` — no dedicated unit test (only indirect via controller tests).
- **T4.** M4's fallback-toast identical-copy case; M5's stale-chrome case; H1's >240-byte width map; the parity fixtures listed in L22.

---

## Suggested execution order for the fix agent

1. **H1** (renderer crash — implement the xxh3 long path or guard + document).
2. **R1, R2, R3, R6** (versions, changelog, parity manifest, pest memory) — mechanical, unblock everything else. R4/R5 are decisions for Jacob; flag them, don't guess.
3. **M11** (fix the parity harness stripping), regenerate goldens, then **M12–M14** and **L16–L21** in one renderer-parity pass with the new fixtures (L22) — every change mirrored React ↔ Vue and verified against Blade via the parity suite.
4. **M1–M3** (canvas color tokens, font-mutation cache invalidation, scope rewriter) — the three editor/site-editor correctness items.
5. **M6–M10** (Font Library PHP: faces cap/budget, rollback re-check, preview cache ceiling, Bunny parser pass, translated exceptions).
6. **M4, M5** (composed-view toast latch + stale chrome).
7. **L-tier** in file-cluster order (all Font Library PHP lows together, all JS lows together, style/docs nits last).
8. **T-tier** tests alongside their related fixes; standalone ones last.
9. Full verification: `php -d memory_limit=1G vendor/bin/pest`, `npm test`, `npm run build:lib && npm run build`, `npm run verify:parity`. Then pause for Jacob's manual browser pass (including the L9 live-endpoint smoke test) before any commit/push.

---

## Areas verified solid (no action)

- **Font Library security posture:** SSRF closed (https + host-suffix allowlists, no redirects, TLS verification, no user-supplied URLs anywhere in the proxy), bounded streaming reads (64KB chunks / 15MB ceiling), magic-byte + server-derived-extension upload gating, `Str::slug`-built paths (no traversal), atomic fonts.css writes with random temp names, `VariableFontMetadataParser` genuinely defensive (bounded inflation, axis caps, UIntBase128 overflow rejection, top-level Throwable catch), authz double-gated (FormRequest + controller + registered `FontPolicy`), rate limits on all remote-touching routes, no secrets (keyless by design), Eloquent-only queries.
- **CSS value whitelist (#720/#721):** byte-identical grammar across React/Vue/Blade (verified by line diff), sound against `url()`/`expression`/comment-digraph bypasses, drop-don't-mangle semantics, empty-bucket skip.
- **Font preview CSS escaping** (`cssString`, `quoteFamily`, `escapeUrl`): backslash-first, control-char + `<`/`>` stripping, whitelisted `format()` tokens — no breakout found.
- **Font Library modal:** correct WAI-ARIA tabs (roving tabindex, wrap-around, focus-follows-activation), airtight request-id reducer discipline, cleaned-up debounce/AbortController.
- **`useAppliedTemplate` race handling**, payload validation, split/hydrate pipeline (no round-trip loss possible), template-part cycle guard, ribbon `?inline` CSS delivery.
- **Deep links / routing:** origin-validated route base, `URLSearchParams` construction, digit-gated `entity_id`, pathname+search cancellation guard, correct popstate re-parsing.
- **`installed-fonts-store`** `pendingForce` coalescing fix; **canvas CSS cache** pending-entry identity guard (the in-flight repopulation race is genuinely fixed).
- **Hash ports:** `xxh3.test.ts` covers every ≤240-byte length class against PHP reference digests (H1 is only the >240 path); `phpJsonEncode` correctly mirrors PHP default-flag `json_encode`.
- **`photoGrid.ts` port quality** (phpTrim, ASCII regex classes, pinned declaration order, `_vePhotoGridScope` side-channel stripped) — the model for future ports.
- **Wiring:** policy/gate registration, all routes named + throttled with correct constraints, migrations reversible with sane FKs/uniques, config keys in the published file, hooks follow `ap.visualEditor.*` inline-literal convention.
- **Hygiene:** zero `dd()`/`dump()`/`console.log`/`TODO` leftovers in the diff; JS i18n consistently `__( '…', TEXT_DOMAIN )`.
