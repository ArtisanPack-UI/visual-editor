# Visual Editor v1.6.0 Release Review — `release/1.6` vs `main`

- **Date:** 2026-08-07
- **Repo:** `~/Code/ArtisanPack UI Packages/visual-editor` (quote the path — it contains a space)
- **Scope reviewed:** `git diff origin/main...origin/release/1.6` — 42 commits (10 merged PRs), 143 files, +12,312 / −271
- **Merged PRs:** #698 (templates preview / composed view), #701 (#700 per-block layout classes), #703 (#702 React/Vue layout compound), #705 (#699 renderer-blade CSS 404), #706 (#695 canvas theme colors), #707 (#679 scope global styles), #708 (#690 core/html), #709 (#691 marquee sourced attrs), #710 (#692 container-binding backslash), #713 (#704 markup parity harness)

## Verdict

**Not ready to tag yet.** The code itself is in good shape — no security or data-loss defects were found, and both test suites and both production builds are green. What blocks the release is (a) the release-prep chore has not been done (versions, CHANGELOG, docs stamp), and (b) one **major** cross-renderer parity bug (R1) that undermines the headline promise of the new parity harness. Fix the blockers and R1 before tagging; the minors can ship in 1.6.x if desired, but most are 5–15-minute fixes and worth doing now.

## Health snapshot (verified on branch tip `589f5623`)

| Check | Result |
|---|---|
| PHP suite (`composer test`) | 1,757 passed, 1 conditional skip (cms-framework 2.5+ gate), 4,601 assertions |
| JS suite (`npm test`) | 336 files, 3,172 tests, all passed (but see R4 — some are stale vendored duplicates) |
| `npm run build:lib` | ✅ |
| `npm run build` | ✅ (pre-existing chunk-size warnings only) |
| Branch vs main | 42 ahead, **0 behind** — no back-merge needed |
| Release process | Manual: merge release PR into `main`, then `gh workflow run release.yml -f version=1.6.0` (workflow builds `dist/`, tags once — see comments in `.github/workflows/release.yml`) |

---

## BLOCKERS — release prep not done

The prior release's prep commit (`2ae36e24`, "chore: prepare 1.5.5 release") defines the checklist convention. None of it has happened for 1.6.0.

### B1. Version bumps missing

- `composer.json` line 4: `"version": "1.5.5"` → `"1.6.0"`
- `package.json` line 3: `"version": "1.5.5"` → `"1.6.0"`
- Note: `packages/visual-editor-renderer-react/package.json` and `packages/visual-editor-renderer-vue/package.json` sit at `1.1.0`. These are versioned independently (last bumped in their own "prepare 1.1.0 release" commit). Given both renderers changed behavior on this branch (#702), bump both to `1.2.0` — or confirm with the maintainer that sub-package versions ride separately and skip.

### B2. CHANGELOG `[Unreleased]` not converted, and four PRs are missing entirely

1. Retitle `## [Unreleased]` in `CHANGELOG.md` to `## [1.6.0] - <release date>` (keep an empty `[Unreleased]` header above it if the file convention does — check: 1.5.5 did not keep one).
2. The existing entries cover #704, #690, #695, #699, #700, #702. **Missing entries for four PRs:**
   - **#698 — Templates preview / composed view.** This is the headline *feature* of 1.6 and has zero changelog presence. Write an `### Added` entry. Source material: `docs/post-editor/Composed-View.md` (new, 200 lines) and the PR body (`gh pr view 698`). Cover: the composed-view toggle in the post-editor top bar, template chrome rendered as inert previews around the content slot, the applied-template API endpoint (`ResourceAppliedTemplateController`), fallback behavior when a template is broken/missing, and the `?template=` preview override.
   - **#679 — Global-styles `:root` selectors scoped to the site-editor canvas** (`scope-global-styles-css.ts`). `### Fixed` entry: root-level global styles previously leaked onto the admin chrome.
   - **#691 — `artisanpack/marquee` registered so its sourced attributes recover.** `### Fixed`.
   - **#692 — Container bindings resolved without a leading backslash** (`EntitySearchController` + sweep of other FQCN sites). `### Fixed`: host apps that rebound `TemplateResolver::class` were silently ignored.
3. Match the house style: bolded lead, `(#issue)`, explanation of cause and consequence (read the existing 1.6/1.5.5 entries for tone).

### B3. Docs version stamp stale

- `docs/home.md` line 180: `*This documentation covers visual-editor v1.5.5*` → `v1.6.0`.
- While in docs: confirm `docs/post-editor.md`, `docs/site-editor.md`, `docs/post-editor/Composed-View.md` (all touched on this branch) don't reference "unreleased" phrasing. They looked fine on inspection; this is a re-read, not a rewrite.

---

## R1. MAJOR — React/Vue Group renderers miss Blade's #595 flex-panel layout override

- **Files:** `packages/visual-editor-renderer-react/src/blocks/core/layout.tsx` (~L31–45), `packages/visual-editor-renderer-vue/src/blocks/core/layout.ts` (~L36–48). Reference behavior: `packages/visual-editor-renderer-blade/resources/views/blocks/core/group.blade.php` L20–33.
- **Defect:** Blade flips the group's layout class to `is-layout-flex` (and now `wp-block-group-is-layout-flex`) when the base-breakpoint `ap-flex` class is present and `layout.type` is empty (#595 fix). React and Vue call `layoutClass(attributes)` unconditionally and emit `is-layout-flow wp-block-group-is-layout-flow` for the same tree.
- **Consequence:** A group using the Flex Layout panel (`artisanpackFlex` → `ap-flex`) with no explicit `layout` gets the flow baseline rule `is-layout-flow > * + * { margin-block-start: gap }` in React/Vue SSR — flex children pushed apart, which is exactly the bug #595 fixed for Blade. And the brand-new parity harness (#704) does **not** catch it because no fixture carries `artisanpackFlex`. That gap contradicts the harness's own changelog claim that drift "fails CI at the point it is introduced."
- **Fix (step by step):**
  1. In both `GroupBlock` implementations: compute the raw stored type — `attrString(attrRecord(attributes.layout).type).trim()` — and the flex classes first, then:
     ```ts
     let cls = layoutClass(attributes);
     if (rawType === '' && flexClasses.includes('ap-flex')) {
         cls = 'is-layout-flex';
     }
     ```
     and pass `cls` into `layoutPair('group', …)`. Mirror `group.blade.php` exactly: only the unprefixed base `ap-flex` token triggers it, and only when the stored type string is empty.
  2. Keep React and Vue byte-identical mirrors of each other (existing convention in these two packages).
  3. Add a fixture `group-flex-panel` to `packages/renderer-markup-parity/fixtures.json`: a group with an `artisanpackFlex` payload that emits base `ap-flex`, and no `layout` attribute.
  4. Regenerate goldens: `composer test:update-markup-goldens`; review the new golden by hand (it should show `is-layout-flex wp-block-group-is-layout-flex`).
  5. Verify: `npx vitest run packages/renderer-markup-parity packages/visual-editor-renderer-react packages/visual-editor-renderer-vue` and `./vendor/bin/pest packages/visual-editor-renderer-blade/tests/Feature/RendererMarkupParityTest.php`.

---

## Minor findings

### R2. Canonicalizers not symmetric on non-ASCII whitespace (NBSP)

- **Files:** `packages/visual-editor-renderer-blade/tests/Support/CanonicalMarkup.php` L310 (`/\s+/u`) vs `packages/renderer-markup-parity/canonicalize.ts` L193 (`/\s+/g`).
- JS `\s` matches U+00A0/U+2028/U+FEFF; PHP `\s` under `/u` without `(*UCP)` matches ASCII whitespace only. Any future fixture containing `&nbsp;` produces a golden the JS side can't match — a phantom "renderer drift" failure.
- **Fix:** In `canonicalize.ts`, use `value.replace(/[ \t\r\n\f\v]+/g, ' ')` to match PHP's semantics (or add `(*UCP)` on the PHP side — pick one and mirror). Add a paragraph fixture containing `&nbsp;` to prove it, regenerate goldens.

### R3. Divergence-regex plumbing: unescaped `#` delimiter + no compile validation in PHP

- **Files:** `CanonicalMarkup.php` L291 (`preg_match( '#' . $pattern . '#', … )`) vs `canonicalize.ts` L52 (`new RegExp(source)`); patterns come from `fixtures.json` → `knownDivergences[].dropClassTokensMatching`.
- A future pattern containing `#` breaks PHP compilation silently (warning → treated as no-match) while JS compiles fine → golden written *with* the token, JS drops it, permanent baffling parity failure.
- **Fix:** In `CanonicalMarkup::isDeclaredDivergentClass()`, escape the delimiter (`str_replace('#', '\\#', $pattern)`) and throw if `@preg_match` returns `false`. Add an assertion to the existing "no orphaned goldens" Pest test that every `dropClassTokensMatching` compiles, plus a mirror vitest assertion via `new RegExp`. (Current sole pattern `^ve-w-[0-9a-f]{4,}$` is safe.)

### R4. `npm test` executes stale vendored duplicates of renderer suites

- **File:** `vitest.config.ts` — the `packages/**/tests/**` include glob sweeps `packages/visual-editor-renderer-blade/vendor/artisanpack-ui/visual-editor/packages/…`, a physical stale copy (dated Jul 14). The green run above included an outdated 43-test copy of `parity.test.ts` alongside the current 44-test one.
- Risk both ways: stale copies can fail on code that no longer exists, or keep asserting removed behavior and lend false confidence.
- **Fix:** Add `exclude: ['**/node_modules/**', '**/vendor/**']` to the `test` block of `vitest.config.ts`; rerun `npm test`; confirm no `…/vendor/…` test files appear in the output.

### R5. JS post-template honors a phantom `layoutType` attribute Blade never reads

- **Files:** `packages/visual-editor-renderer-react/src/blocks/core/query.tsx` ~L86 and the Vue mirror; Blade `post-template.blade.php` reads only `$attributes['layout']`.
- Repo-wide, nothing writes or reads `layoutType` except these two lines. A tree carrying `{ layoutType: 'grid' }` renders grid markup in React/Vue but flow/list in Blade — unfixtured, silent divergence.
- **Fix:** Delete the `layoutType` branch from both JS `PostTemplateBlock`s (preferred). Run the JS renderer suites to confirm nothing depended on it.

### R6. `columns` clamp asymmetry on post-template

- **Files:** `query.tsx` L40–49 clamps columns to [1, 12]; `post-template.blade.php` L9 emits `(int)` unclamped (`columns-0`, `columns-99` possible in Blade only).
- **Fix:** Clamp in the Blade partial: `max( 1, min( 12, (int) ... ) )`. Add an out-of-range fixture (`columns: 20`) to `fixtures.json`; regenerate goldens.

### R7. PhotoGrid wrapper classes are a Blade-only drift channel, undeclared

- **Files:** `group.blade.php` L35 (`PhotoGridSupport::wrapperForBlock`); no photo-grid symbol exists in either JS renderer.
- **Fix (choose one):** port photo-grid wrapper emission to both JS renderers + fixture; **or** add a `knownDivergences` entry in `fixtures.json` with a tracking issue and a `dropClassTokensMatching` pattern for photo-grid tokens so the divergence is declared and the rest of group markup stays compared. The second option is the right scope for a release branch.

### R8. Asset-route traversal tests never exercise the `realpath()` containment guard

- **File:** `packages/visual-editor-renderer-blade/tests/Feature/AssetRouteTest.php` ~L106–115.
- Every traversal payload ends in a non-allow-listed extension, so the extension check 404s first and the `str_starts_with(realpath…)` guard in `routes/assets.php` (~L62–64) has zero coverage on the path where it's the only defense. (The route itself was verified traversal-safe — this is test-coverage hardening, not a vulnerability.)
- **Fix:** In a new test case, write a fixture `outside.css` one level above `resources/assets` in `beforeEach`, request a traversal path ending in `.css` that resolves to it, assert 404, delete in `afterEach`.

### R9. Composed-view fallback toast swallowed for a repeated identical failure

- **File:** `resources/js/visual-editor/editor/composed-view/use-fallback-toast.ts` L41–72.
- The `notifiedRef` latch only clears when leaving composed mode, not on a successful resolution — broken template A → working B → broken A again produces no second toast (info still visible via the standing notice, so low harm).
- **Fix:** When `composedFallbackNotice(state)` is `null` **and** `state.status === 'ok'`, set `notifiedRef.current = null` before returning (leave `idle`/`loading` deduped). Add a broken → ok → same-broken test to `use-fallback-toast.test.tsx` expecting two toasts.

### R10. `useAppliedTemplate` cache contradicts its documented contract

- **File:** `resources/js/visual-editor/editor/composed-view/use-applied-template.ts` L3–5, L59–65.
- Doc says results are cached "per (resource, id, template) triple for the lifetime of the editor mount," but `cacheRef` holds one entry — flipping template A → B → A refetches A. Also the key `` `${…}::${template ?? ''}` `` collapses `undefined` and `''` (currently unreachable, latent).
- **Fix:** Change `cacheRef` to a `Map<string, AppliedTemplateState>` keyed by the cache key (`.get()` in the effect, `.set()` in `run`), and disambiguate the key with a sentinel for `undefined` (e.g. `' unset'`). Extend the existing cache test to A → B → A asserting fetch count. (Cheaper alternative: just fix the doc comment — but the Map is ~5 lines.)

### R11. Invalid host `routeBase` silently rewrites SPA navigation to the package default path

- **Files:** `resources/js/visual-editor/site-editor/deep-link.ts` (`normalizeRouteBase`, L108–116); `use-site-editor-routing.ts` L63–70, L121–127.
- A base failing the leading-slash test (relative path, absolute URL) is silently replaced with `/visual-editor/site` — every in-SPA navigation then pushes URLs the host doesn't serve, and reload 404s. The substitution is correct as an href-injection guard for the ribbon CTA, wrong as silent behavior for the SPA's own routing.
- **Fix:** Keep `normalizeRouteBase` for `buildTemplateDeepLink`. In `use-site-editor-routing.ts`, `console.warn` once naming the rejected value when substitution happens, and document the fallback in the `routeBase` docblock in `site-editor-app.tsx`/`main.tsx`. Add a `parseSiteEditorPath` test with a non-`/`-prefixed base pinning the behavior.

### R12. Deep-link "user has navigated" guard compares pathname only

- **File:** `resources/js/visual-editor/site-editor/use-deep-link.ts` L107–111.
- Navigating away and back in-SPA while the slug lookup is in flight defeats the guard; a late resolution then teleports the user. Low likelihood/harm.
- **Fix:** Also compare `window.location.search` (the deep-link query still being present is the real "nothing has happened yet" signal, since `landOnEntity`/`landOnIndex` clear it via replace), or track a navigation counter incremented in `navigate` + popstate. Add a test.

### R13. Wrong `@since` in new testing migration

- **File:** `database/migrations/testing/2026_07_14_000000_create_test_applied_template_table.php` L15: `@since 1.1.0` → `@since 1.6.0` (all other new files on the branch correctly say 1.6.0).

---

## Verified sound (do not re-litigate)

- **`routes/assets.php` (#699) is traversal-safe:** route-param character allowlist, css/js extension allowlist via `pathinfo`, `realpath()` + `str_starts_with` containment, `nosniff` + explicit Content-Type, `max-age=0, must-revalidate` with ETag/304 (tested). All bundled assets are committed, so the no-publish fresh-install path genuinely works. No auth middleware — correct for public front-end assets.
- **`core/html` (#690) raw output is intentional and bounded:** trust boundary documented in the partial (theme files / patterns / authorized editor content only), matches the existing `{!! !!}` convention. *Release-notes advisory:* unlike WordPress there is no `unfiltered_html`-style per-user capability gate, so hosts with low-trust author roles should sanitize on save — worth a sentence in the changelog entry for #690.
- **`ResourceAppliedTemplateController` auth is correct:** inside the `['api','auth']` group, `Gate::authorize('view', …)` before resolution including the `?template=` override path; 401/403/404, recursion (`$visited` set), and core→fork rewriting all covered by a 593-line test file.
- **`TemplateAdapter` core→fork rewrite is read-envelope only** — DB `block_content` keeps `core/*` names (asserted); raw-string forking only touches `template-part`; ordering after `NavigationBlockRefResolver` preserved.
- **#700/#702 layout-class pairing is consistent end-to-end** (type allowlist, row/stack keyed on `group`, `ThemeJsonTokensCompiler` constrained-children rules keyed on the compound to avoid double-constraining, masonry deliberately unpaired).
- **Parity harness mechanics:** goldens only written under `UPDATE_MARKUP_GOLDENS=1`; missing golden fails both suites; orphaned goldens caught by a dedicated Pest test; both canonicalizers symmetric on attribute sorting, comments/PI, escapes, class-token order, style-declaration normalization; CI runs both sides.
- **Composed view (#698):** no write-back surface — chrome rendered as inert previews in isolated stores; content `value` provably untouched (no-remount/no-dirty test); split/hydrate edge cases (missing slot, nested slot, malformed elements, PHP `[]` coercion, `createBlock` throw) all tested; API payloads structurally validated before use; `useAppliedTemplate` race handling (stale-drop via `latestKeyRef` + abort) correct.
- **`scope-global-styles-css.ts` (#679):** `:root` rewritten inside `@media`/`@supports`, comments/strings/`url()` skipped, specificity preserved, no escape path in emitter-shaped CSS.
- **`canvas-color-tokens.ts` / `css-color-parse.ts` (#695):** value allowlist blocks `;{}*` injection, balanced-paren check prevents spill-over, `:root` emission iframe-scoped, parser handles clamping/angles/named colors/malformed → null.
- **No XSS in the editor diff:** no `dangerouslySetInnerHTML`; style injection is text-node based; deep-link hrefs guarded and slugs URL-encoded.
- **Template-part cycle stop, bindings-context `useEffect` move (StrictMode-safe), delete-dialog usage-error precedence, top-bar `role="switch"` pattern** — all correct and tested.
- **FQCN backslash sweep (#692)** is behavior-preserving for `class_exists` and strictly better for container resolution; new tests fail against the old code.

---

## Suggested execution order for the fixing agent

1. **R4** first (vitest `exclude`) — so every later `npm test` run is trustworthy.
2. **R1** (flex-panel parity) + its fixture; regenerate goldens.
3. **R5, R6, R7** (remaining parity symmetry items) + fixtures; regenerate goldens once at the end of this group.
4. **R2, R3** (canonicalizer hardening) + the `&nbsp;` fixture.
5. **R8–R13** (test hardening, editor-JS minors, `@since`).
6. **B2** changelog (now it can also mention the parity fixes), **B1** versions, **B3** docs stamp — release prep last so the changelog captures any of the above you fold in.
7. Full verification: `composer test`, `npm test`, `npm run build:lib && npm run build`, `vendor/bin/php-cs-fixer` if configured (note: this package has **no** style tooling — match surrounding style by hand).
8. Commit as `chore: prepare 1.6.0 release` (matching `2ae36e24`'s shape), push, open the release PR to `main`. Tagging happens post-merge via `gh workflow run release.yml -f version=1.6.0` — **do not push a tag manually** (Packagist immutability, see release.yml header comments).

## Open questions for the maintainer

1. Should the renderer sub-packages (`visual-editor-renderer-react`/`-vue`, currently 1.1.0) bump to 1.2.0 with this release? They changed behavior (#702).
2. R7 (photo-grid): port to JS renderers now, or declare the divergence with a tracking issue? (Declaring is recommended for release scope.)
3. R10: fix the cache to match the doc (Map), or fix the doc to match the cache?
