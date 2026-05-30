# Visual Editor — Block Visibility (Contextual)

**Package:** `artisanpack-ui/visual-editor`
**Version Target:** 1.x
**Created:** 2026-05-30
**Status:** Planning
**Reference:** [Block Visibility (WordPress plugin, by Nick Diego)](https://github.com/ndiego/block-visibility)
**Related:**
 - [`22-block-visibility-user-and-auth.md`](22-block-visibility-user-and-auth.md) — sibling feature: user role / login state. Sits under the same Visibility panel UI; deferred to its own release for scope reasons.
 - [`23-block-visibility-scheduling.md`](23-block-visibility-scheduling.md) — sibling feature: date/time scheduling. Same UI, deferred separately.
 - [`07-permissions-locking.md`](07-permissions-locking.md) — block-level locking, distinct from visibility (locking affects editing; visibility affects rendering).

---

## 1. Problem Statement

Every block on a page is rendered to every visitor. There's no way for an editor to say "show this banner only on screens larger than 768px," "hide this widget when this query string is set," "show this CTA only to referrals from twitter.com," or simply "hide this block without deleting it (yet)." Marketing teams build a lot of pages where some block instances are situational.

The [Block Visibility WordPress plugin](https://github.com/ndiego/block-visibility) is the canonical reference for this feature in the Gutenberg ecosystem. We port the controls that aren't already covered by other systems in our stack (permissions, post scheduling).

## 2. Target User

Marketing/content editors who want context-dependent rendering on a per-block basis without engineering involvement, and developers who want the contextual checks to run server-side (no client flash-of-content) where possible.

## 3. User Stories

- As an editor, I want a master "Hide block" toggle that removes the block from the rendered output without deleting it from the post.
- As an editor, I want to show or hide a block based on the current viewport width using the same breakpoints my responsive design tools use.
- As an editor, I want to show or hide a block based on a query string match (e.g. show only when `?utm_source=newsletter`).
- As an editor, I want to show or hide a block based on the referrer (e.g. show only when the visitor came from `twitter.com`).
- As an editor, I want to show or hide a block based on the visitor's browser / OS / device type (mobile / tablet / desktop / bot).
- As an editor, I want a clear visual indicator in the editor canvas when a block has any visibility rule active (an icon badge on the block toolbar).
- As an editor, I want the editor preview to optionally simulate the rules (e.g. preview what a mobile visitor on a Twitter referral would see).
- As a developer, I want the contextual rules to be evaluated server-side when the input is available at request time (referrer, query string, user-agent) and client-side only for inputs that aren't (viewport width).

## 4. Scope

### 4.1 In scope (v1.x)

- **Hide Block (master toggle)** — a single boolean attribute (`hidden: true`) that completely omits the block from rendered output. Renderer never emits markup for hidden blocks. Editor canvas dims the block (50% opacity + hatched overlay) and shows an "Eye off" icon in its block toolbar.
- **Screen Size visibility** — per-breakpoint show/hide using the same registry as [`17-responsive-design-tools.md`](17-responsive-design-tools.md). The control is a checkbox grid: one cell per registered breakpoint, plus a `base` cell. Rule: hidden if the active breakpoint's cell is unchecked, falling back through the mobile-first cascade for unset cells. Implemented as **CSS** (`@media (min-width:...) { display:none }` for each unchecked cell) — no JS runtime needed.
- **Query String visibility** — list of `key=value` (value optional) or `key=*` (any value) clauses, with show/hide direction (`Show if matches` / `Hide if matches`) and `Match any` / `Match all` combinator. Evaluated server-side from the request's parsed query.
- **Referrer visibility** — list of referrer host patterns (literal hostnames, optionally prefixed `*.` for subdomains; plus the magic value `(direct)` to match empty Referer). Show/hide direction + Any/All combinator. Evaluated server-side from `Referer` request header.
- **Browser / OS / Device visibility** — lists of browsers (Chrome / Safari / Firefox / Edge / Opera / Other), OSes (macOS / Windows / Linux / iOS / Android / Other), and device types (Mobile / Tablet / Desktop / Bot). Show/hide direction. Evaluated server-side via a small UA parser (`jenssegers/agent` is the proposed dep — already common in Laravel apps; falls back to a tiny in-repo parser if not present).
- **Visibility Panel UI** — an Inspector "Visibility" panel grouping all rules. Master toggle at top; expandable subsections per rule family. Each subsection has a small "Active rule" badge when configured.
- **Per-block toolbar badge** — when any rule is active, an "Eye" badge appears next to the block movers. Clicking it opens the Visibility panel.
- **Visibility Preview mode** — a Site Editor toggle that lets the editor mock the inputs (viewport, query string, referrer, browser, device) and previews what a visitor in that state would see. Editor-only; never persists to saved content.
- **Logging hook** — a hook (`ap.visibility.evaluated`) fires after each evaluation in dev/debug mode with the rule + outcome, so debugging "why is this block hidden?" is straightforward.
- **Site-wide kill switch** — config flag `visual-editor.visibility.enabled` (default true). Tests, ops debugging, and emergency rollbacks can flip it off.

### 4.2 Out of scope

- **User role / login state visibility** — see [`22-block-visibility-user-and-auth.md`](22-block-visibility-user-and-auth.md). Same UI surface, separate scope.
- **Date/time scheduling** — see [`23-block-visibility-scheduling.md`](23-block-visibility-scheduling.md). Same UI surface, separate scope.
- **A/B split visibility (random N% of traffic)** — out of scope; experimentation belongs to the planned A/B Testing component (Phase 5, `08-additional-features.md`).
- **Geolocation / IP-based visibility** — out of scope; would require an IP-geo service dependency.
- **Cookie / localStorage visibility** — out of scope; would require client-side evaluation and re-render.

## 5. Behavior

### 5.1 Happy path (Screen Size)

1. Editor selects a promotional Banner block. They open the Visibility panel, expand **Screen Size**, and uncheck `base` and `sm` (so the block is hidden on mobile, visible on `md` and up).
2. Save. The renderer emits the block markup wrapped in a class (`ap-vis-hide-base ap-vis-hide-sm`) that maps to `@media (max-width: 767px) { .ap-vis-... { display: none; } }`.
3. The published page hides the banner on phones and shows it on tablet/desktop. Pure CSS — no JS runtime, no layout shift.

### 5.2 Happy path (Query String)

1. Editor selects a newsletter CTA, opens Visibility → **Query String**, adds rule `utm_source = newsletter`, direction `Show if matches`.
2. Save. Renderer evaluates `request()->query('utm_source') === 'newsletter'`. If false, the block is not emitted.
3. Visitors arriving without the `utm_source` query never see (or download) the block markup.

### 5.3 Edge cases

- **Multiple rule families active simultaneously.** All families combine with AND — every family that's configured must return "visible" for the block to render.
- **Query string rule with no value** (`utm_source` alone). Matches any non-empty value for the key.
- **Bot referrer evaluated for SEO crawlers.** Bots typically have no referrer; `(direct)` clause matches.
- **Visibility evaluates to hidden on the canvas during edit.** The block stays visible in the canvas (with the hatched overlay) so the editor can edit it; visibility only affects the rendered output on the public site and the Visibility Preview mode.
- **Editor sets unreachable rule** (hidden on every breakpoint). Inspector shows a warning: "Block will never be visible to visitors."
- **Configured rule references something later removed** (e.g. a deleted breakpoint). Audit command surfaces the orphaned rule; renderer treats unknown breakpoints as `null` (no effect).
- **Server can't determine an input** (e.g. behind a CDN that strips Referer). Configured `(direct)` clauses match; pattern-based clauses don't match.

## 6. Acceptance Criteria

- [ ] Master Hide Block toggle removes the block from rendered output across Blade, React, and Vue renderers; canvas dims with hatched overlay.
- [ ] Screen Size rule emits CSS `display:none` per breakpoint without JS; respects the registered breakpoint set including custom breakpoints.
- [ ] Query String rule evaluates server-side against the request; supports `key=value`, `key=*`, multiple clauses with Any/All combinator and show/hide direction.
- [ ] Referrer rule evaluates server-side; supports literal hostnames, `*.` subdomain wildcards, and `(direct)`.
- [ ] Browser/OS/Device rules evaluate via `jenssegers/agent` when available; fall back to in-repo UA parser otherwise.
- [ ] Visibility Panel renders only the family subsections; active rules show a badge.
- [ ] Block toolbar Eye badge appears when any rule is active.
- [ ] Visibility Preview mode mocks all five rule families and reflects the result on the canvas.
- [ ] Site-wide kill switch (`config('artisanpack.visual-editor.visibility.enabled')` set to false) bypasses every rule.
- [ ] All `artisanpack/*` blocks opt in by default; opt-out via `supports.artisanpackVisibility: false` removes the panel.
- [ ] Pest tests cover every rule family's evaluator with happy-path and edge-case inputs.
- [ ] Vitest tests cover the Visibility Panel UI + the block toolbar badge.
- [ ] Playwright E2E covers a full editor → save → published-page flow for each rule family.
- [ ] Docs in `docs/visibility.md` cover authoring rules, the preview mode, the hook, and the kill switch.

## 7. Implementation Notes

### 7.1 Files to create

- `src/Visibility/VisibilityEvaluator.php` — orchestrates per-block evaluation; returns `visible | hidden`.
- `src/Visibility/Rules/HiddenRule.php`
- `src/Visibility/Rules/ScreenSizeRule.php` — produces CSS class names + scoped @media rules.
- `src/Visibility/Rules/QueryStringRule.php`
- `src/Visibility/Rules/ReferrerRule.php`
- `src/Visibility/Rules/UserAgentRule.php` — wraps `jenssegers/agent` if installed, else uses `src/Visibility/Support/MiniUaParser.php`.
- `src/Visibility/Support/MiniUaParser.php` — fallback UA parser.
- `resources/js/visual-editor/visibility/VisibilityPanel.tsx`
- `resources/js/visual-editor/visibility/PreviewControls.tsx` — Site Editor preview-state mocker.
- `resources/js/visual-editor/visibility/ToolbarBadge.tsx`
- `packages/visual-editor-renderer-{blade,react,vue}/src/visibility/` — per-renderer integration. Blade renderer skips emission entirely when hidden. React/Vue renderers do the same on server render; client-side these skip the component when the resolved visibility is hidden.
- `tests/Unit/Visibility/*`, `tests/Feature/Visibility/*`, Vitest + Playwright suites.

### 7.2 Files to modify

- `config/visual-editor.php` — add the `visibility` key (enabled flag, hook registration).
- `src/VisualEditorServiceProvider.php` — register `VisibilityEvaluator`, fire `ap.visibility.evaluated` hook when in debug.
- All forked `block.json` files — add `supports.artisanpackVisibility: true`.
- `composer.json` — add `jenssegers/agent` as a *suggest*, not a hard require (fallback parser keeps the package light).
- `docs/theming.md` — short note that visibility CSS uses the breakpoint registry.

### 7.3 Database / schema

No DB migrations. Visibility rules live in the block attribute tree (`artisanpackVisibility: { hidden, screenSize, queryString, referrer, userAgent }`).

### 7.4 Dependencies

- Optional: `jenssegers/agent` for UA parsing; bundled fallback otherwise.

## 8. Open Questions

- Should we ship a "show in editor only" / "show in published only" toggle as part of the master toggle? (Tentative: yes — useful for editor-only annotations / TODOs. Add as a sub-toggle inside Hide Block.)
- Do we want the preview-mode mocked inputs to persist across editor sessions (per user)? (Tentative: yes, store in `localStorage`.)
