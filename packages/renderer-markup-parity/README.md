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

## Scope: markup, not CSS

The check compares block markup only. Renderer-injected
`<style data-ve-*>` blocks are stripped from both sides, because the
layout baseline CSS is a *known intentional* divergence: the JS
renderers' `LAYOUT_BASELINE_CSS` carries three constrained-group
containment rules that the Blade baseline does not, since Blade emits
them from `ThemeJsonTokensCompiler::compileLayoutRules()` gated on the
`theme.json` layout sizes (see the header comment in
`layoutBaselineCss.ts`). Comparing the CSS here would only encode that
difference a second time.

## Known divergences

### Encoded in `fixtures.json`

`knownDivergences` declares class tokens that one renderer emits and the
others do not, with the reason and the tracking issue. Matching tokens
are dropped from every class attribute on both sides before comparison —
narrow and explicit, rather than loosening the match. Currently:

- **`ve-w-<hash>` (#712, introduced by #487).** `core/column`'s Blade partial mints a hashed
  scope class and pushes matching `flex-basis` / `flex-grow`
  `!important` rules into the per-request responsive accumulator, so an
  explicit column width survives WP core's mobile stacking rule. React
  and Vue emit the inline `flex-basis` only. Dropping the token keeps the
  rest of the column markup — including the inline style — under
  comparison instead of dropping the width fixture entirely.

### Not covered by a fixture

- **`group` with the #595 Flex Layout panel active at the base
  breakpoint.** `group.blade.php` flips its layout class to
  `is-layout-flex` when `artisanpackFlex` emits the unprefixed `ap-flex`
  class; React's `GroupBlock` and its Vue twin have no equivalent, so the
  same tree renders `is-layout-flow` there. This is a genuine
  pre-existing divergence rather than an intentional one, so it is
  deliberately *not* normalized away here — it needs a fix in one of the
  renderers, tracked in #711. Add a fixture for it once the renderers
  agree.
