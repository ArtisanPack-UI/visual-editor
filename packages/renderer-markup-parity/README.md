# Renderer markup parity (#704)

The Blade, React and Vue renderers are meant to be interchangeable: the
same block tree must produce the same markup through any of them. Two
checks already guard part of that contract —
`scripts/verify-renderer-parity.mjs` (same block *names* registered
everywhere) and `packages/visual-editor-renderer-vue/tests/parity.test.ts`
(byte-identical HTML between **React and Vue**). Neither compares Blade's
markup against the JS renderers', which is how #700 could land a
Blade-only fix that silently made Blade disagree with both JS renderers
until #702 was filed.

This directory closes that gap.

## How it works

- **`fixtures.json`** is the single, language-neutral source of truth: a
  list of block trees, plus the manifest of known renderer divergences. A
  fixture added here is automatically exercised against all three
  renderers.
- **The Blade suite writes the goldens.**
  `packages/visual-editor-renderer-blade/tests/Feature/RendererMarkupParityTest.php`
  renders each fixture through `<x-ve-blocks>`, canonicalizes it, and
  compares it against `goldens/<name>.txt`.
- **The JS suite reads them.** `tests/blade-parity.test.ts` renders each
  fixture through the React and Vue SSR entry points, canonicalizes with
  the same algorithm, and asserts the result equals the golden.

So a divergence introduced in Blade fails the Pest suite (golden
mismatch), and one introduced in React or Vue fails the vitest suite.
Both suites already run in CI on every PR (`test-php` and `test-js` in
`.github/workflows/ci.yml`), so no separate job is needed.

## Regenerating the goldens

Only when the markup change is intentional, and only after reviewing the
diff — a golden update is the reviewable record that the contract moved:

```bash
composer test:update-markup-goldens
```

Then re-run the JS side to confirm React and Vue agree with the new
markup:

```bash
npm test -- packages/renderer-markup-parity
```

## Canonicalization contract

`canonicalize.ts` and its PHP twin
(`packages/visual-editor-renderer-blade/tests/Support/CanonicalMarkup.php`)
implement the same algorithm. Keep them in sync — if they drift, the two
suites stop agreeing about what the goldens mean.

Normalized (insignificant across renderers):

- **Whitespace.** Runs collapse to a single space; whitespace-only text
  nodes are dropped; the leading/trailing space of an element's first and
  last text child is trimmed.
- **Attribute order.** Attributes are sorted by name.
- **`class` whitespace.** Collapsed and trimmed — token spelling and
  order are preserved exactly.
- **`style` separators.** Each declaration is trimmed and re-joined with
  `; `, and the whitespace around the property/value `:` is normalized —
  Vue's server renderer emits a trailing `;` and React writes
  `flex-basis:60%` where Blade writes `flex-basis: 60%`. Property names,
  values and declaration order are preserved exactly.
- **Comments.** Dropped, which also removes Vue's SSR fragment markers.

Not normalized: element names, class tokens, attribute names, attribute
values.

## Scope: markup and per-instance CSS

The check compares block markup **and** the renderer's per-instance CSS
(column widths, Photo Grid metrics, arbitrary Flex values). The delivery
differs by renderer — Blade folds every per-instance rule into one
`<style data-ve-responsive>` block (plus `data-ve-flex-arbitrary`), while
React and Vue split them across `data-ve-column-width`,
`data-ve-photo-grid`, `data-ve-visibility` and `data-ve-flex-arbitrary`
tags — so the harness extracts the rule bodies from whichever tag carries
them, splits them into top-level rules (brace-depth aware, so an
`@media (…) { … }` block stays one rule), collapses insignificant
whitespace, sorts, and appends the result under a
`@@ renderer-instance-css @@` delimiter. Sorting means the two suites
compare rule *bodies*, not which tag or what order each renderer delivers
them in — only a differing declaration surfaces as a failure.
`extractRendererCss` / `canonicalRendererCss` (JS) and their
`markupParity*` twins (Pest) must stay in lockstep, exactly like the
markup canonicalizer.

The **baseline / global-styles / theme** layer is the one thing still
stripped rather than compared (`data-ve-layout-baseline`,
`data-ve-global-styles`, `data-ve-theme`, `data-ve-theme-tokens`,
`data-ve-block-library*`). It is a *known, intentional, one-way*
divergence — see **Known divergences** below.

## Known divergences

### Class tokens encoded in `fixtures.json`

`knownDivergences` declares class tokens that one renderer emits and the
others do not, with the reason and the tracking issue. Matching tokens
are dropped from every class attribute on both sides before comparison —
narrow and explicit, rather than loosening the match.

**The list is currently empty**: every former class-token divergence has
converged. The `ve-w-<hash>` column-width scope (#712/#487) and the
`has-photo-grid` / `photo-grid-<hash>` scope (#714/#594) are now emitted
identically by all three renderers, and their CSS bodies are compared
directly (see **Scope** above) rather than dropped.

### One-way CSS divergences (stripped, not compared)

These live in the baseline / global-styles / theme layer the harness
strips, so they never reach comparison. They are recorded here because
that layer is deliberately excluded, not accidentally:

- **Constrained-group containment + auto margins.** The JS renderers'
  `LAYOUT_BASELINE_CSS` (`layoutBaselineCss.ts`) carries three
  `.wp-block-group-is-layout-constrained` containment rules the Blade
  baseline does not: Blade emits its containment from
  `ThemeJsonTokensCompiler::compileLayoutRules()`, gated on `theme.json`
  declaring `contentSize` / `wideSize`. The JS `max-width` rules fall out
  of `var()` semantics when those custom properties are undefined, but
  the **auto margins are not gated the same way** — with no content size
  configured they still centre a self-sized child, one place the JS
  baseline goes further than Blade's gated output. See the header comment
  in `layoutBaselineCss.ts`.

### Blade-only blocks (no JS `<style>`/markup twin)

- **`core/html` and `artisanpack/html`.** Shipped as Blade partials; the
  React/Vue renderers have no static counterpart and fall back to
  server (`DynamicBlock`) rendering at runtime. They are declared under
  `bladeOnly` in `packages/renderer-parity.json`, which excludes them
  from the React/Vue registration check. They have no markup-parity
  fixture because the two sides render fundamentally different markup by
  design.

### Resolved

- **`group` with the #595 Flex Layout panel active at the base
  breakpoint (#711).** `group.blade.php` flips its layout class to
  `is-layout-flex` when `artisanpackFlex` emits the unprefixed `ap-flex`
  class. React's `GroupBlock` and its Vue twin called `layoutClass()`
  unconditionally and rendered `is-layout-flow` for the same tree, so
  flex children caught the flow baseline's `margin-block-start`. Both JS
  renderers now mirror the Blade override, and three fixtures pin it:
  `group-flex-panel` (base breakpoint flips),
  `group-flex-panel-breakpoint-only` (`md:ap-flex` does not), and
  `group-flex-panel-explicit-layout` (a stored `layout.type` wins).
