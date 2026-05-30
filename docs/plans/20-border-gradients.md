# Visual Editor — Border Gradients

**Package:** `artisanpack-ui/visual-editor`
**Version Target:** 1.x
**Created:** 2026-05-30
**Status:** Planning
**Related:**
 - [`06-global-styles.md`](06-global-styles.md) — gradient token system reused here
 - [`18-state-design-tools.md`](18-state-design-tools.md) — border gradients should be state-aware
 - [`17-responsive-design-tools.md`](17-responsive-design-tools.md) — border gradients should be breakpoint-aware

---

## 1. Problem Statement

Block border styles currently expose a single solid color. Modern design systems lean on gradient borders for emphasis (cards with a hover-glow border, CTAs with a brand-gradient outline). Editors can't author these without dropping into custom CSS, and the resulting CSS doesn't compose with our token system or live-preview correctly.

## 2. Target User

Content editors and designers who want gradient-bordered cards, CTAs, sections, and badges to match their design system, and developers who want gradient borders authored against design tokens (so a token change updates every gradient automatically).

## 3. User Stories

- As an editor, I want to set a block's border to a linear gradient with two or more stops.
- As an editor, I want to set a radial gradient border (centered on the block).
- As an editor, I want to set a conic gradient border (rotating around the block — popular for "glow ring" effects).
- As an editor, I want to use the existing gradient picker from the background color UI for borders, so the controls feel consistent.
- As an editor, I want to set a hover-state gradient that's different from the idle gradient (the gradient animates smoothly between states).
- As a developer, I want gradient borders to honor the registered gradient tokens (defined in `theme.json` → `settings.color.gradients`) so editors can pick brand gradients by name.
- As a developer, I want gradient borders that respect the block's `border-radius` (the gradient hugs rounded corners cleanly, no fringe).

## 4. Scope

### 4.1 In scope (v1.x)

- **Gradient types**: `linear`, `radial`, `conic`.
- **Stops**: minimum 2, no upper limit. Each stop has a color (token reference or hex/oklch literal) and a position (`%` or auto-spaced).
- **Single border**: gradient applies to all four sides as one continuous frame.
- **Border width**: existing `__experimentalBorder.width` control unchanged — width can still be set per-side; gradient maps onto the union frame.
- **Border radius**: existing `__experimentalBorder.radius` control unchanged — the gradient frame clips correctly around rounded corners via the standard `padding-box` mask trick (`background-clip: padding-box, border-box`).
- **Token integration**: the gradient picker shows the theme's registered gradient tokens (`theme.json` → `settings.color.gradients`) above the custom-stops editor. Picking a token references it (CSS custom property) instead of inlining the value.
- **State composition**: when [`18-state-design-tools.md`](18-state-design-tools.md) is enabled for `border.gradient`, a separate gradient can be set per state (`idle`, `hover`, `focus`, etc.). The renderer adds a `transition: border-image 200ms ease` (configurable) so the gradient morphs cleanly.
- **Breakpoint composition**: composes with [`17-responsive-design-tools.md`](17-responsive-design-tools.md). Per-breakpoint gradients are stored in the same attribute shape and resolved by the responsive resolver.
- **Block support opt-in**: declared via the existing `supports.__experimentalBorder` schema, extended with a `gradient: true` flag. All forked blocks that already support borders opt in by default (`group`, `columns`, `column`, `cover`, `media-text`, `image`, `button`, `buttons`, `details`, `callout`).
- **CSS emission strategy** (Blade/React/Vue):
  - Linear gradients → `border-image: linear-gradient(...) 1;` plus a `padding-box` clip fallback when `border-radius > 0`.
  - Radial/Conic gradients → the `padding-box`/`border-box` mask technique (a single absolutely-positioned `::before` carrying the gradient, masked to the border ring). Documented and tested.
  - Tailwind class strings preferred when the gradient maps to a registered token; scoped inline styles otherwise.

### 4.2 Out of scope

- **Per-side independent gradients** (different gradient per top/right/bottom/left). Considered for v2.x — significantly more UI surface and a rare requirement.
- **Animated gradient (gradient rotation/translation over time)**. Tracked separately under [`19-block-animations.md`](19-block-animations.md) as a future continuous-animation preset.
- **Gradient masks / non-rectangular shapes**. Out of scope; would need shape-tracing.
- **Multiple stacked border gradients**. Out of scope.

## 5. Behavior

### 5.1 Happy path

1. Editor selects a card block (`group`).
2. They open the Border panel and click the gradient swatch.
3. The gradient picker opens; they pick the theme's `brand-sunset` linear gradient token.
4. They set border width to `2px` and border radius to `12px`.
5. Save. The renderer emits a `padding-box` clip + `border-image` rule (or the mask `::before` for radial/conic). On the live page, the card has a clean 2px gradient border that follows the 12px radius.

### 5.2 Edge cases

- **Border width is `0`.** Gradient is preserved in attribute storage but no CSS is emitted (nothing to render).
- **Border radius exceeds half the block's smaller dimension** (e.g. a pill shape). The mask still clips correctly; tested at radii up to `9999px`.
- **Gradient token referenced by the block is removed from the theme.** Renderer falls back to the resolved value at the time of the previous render; an editor warning surfaces the dangling token reference.
- **Browser doesn't support `border-image` on rounded corners cleanly** (some older Safari/Chromium versions). Mask `::before` path is used as the default for radial/conic, eliminating the difference.
- **State transition with very different gradient angle** (e.g. idle 45°, hover 135°). The transition interpolates the resulting bitmaps via the standard `border-image` transition; documented as a known limitation when angles differ significantly.

## 6. Acceptance Criteria

- [ ] Linear, radial, and conic gradients can be authored on any opted-in block.
- [ ] Gradients reference theme tokens by name; renaming/removing a token surfaces an editor warning.
- [ ] Border radius clips the gradient correctly across linear/radial/conic.
- [ ] Per-state gradients work and transition smoothly between states.
- [ ] Per-breakpoint gradients work and respect mobile-first cascade.
- [ ] Renderer prefers Tailwind class strings for token-referenced gradients; falls back to scoped inline styles otherwise.
- [ ] All previously border-supporting forked blocks gain the gradient option without breaking existing solid-border configurations.
- [ ] Pest tests cover: attribute storage, schema, server emission per gradient type, token reference resolution.
- [ ] Vitest tests cover: editor InspectorControls roundtrip, gradient picker UX.
- [ ] Playwright visual-regression tests cover: linear/radial/conic at radius 0, 8, 12, 24, 9999.
- [ ] Docs in `docs/border-gradients.md` cover the picker, token integration, and the radial/conic mask technique.

## 7. Implementation Notes

### 7.1 Files to create

- `src/Borders/GradientBorderEmitter.php` — CSS emission per gradient type, including the mask `::before` path.
- `src/Borders/TokenResolver.php` — resolve a token reference into a concrete gradient string.
- `resources/js/visual-editor/borders/GradientBorderControl.tsx` — InspectorControls UI extending the existing border panel.
- `packages/visual-editor-renderer-{blade,react,vue}/src/borders/` — per-renderer integration.
- `tests/Unit/Borders/*`, `tests/Feature/Borders/RendererTest.php`, Vitest + Playwright suites.

### 7.2 Files to modify

- All forked block `block.json` files with `__experimentalBorder` → add `gradient: true` under that support.
- `resources/js/visual-editor/blocks/_shared/inspector-controls/border.tsx` — wire `GradientBorderControl` next to the color swatch.
- `docs/theming.md` — note that registered gradient tokens are picked up by both background and border gradient pickers.

### 7.3 Database / schema

No DB migrations.

### 7.4 Dependencies

None new.

## 8. Open Questions

- Should we expose the transition duration for state changes as part of the State Design Tools `transition` control, or in the Border Gradient sub-panel? (Tentative: State Design Tools owns it; Border Gradient reads it.)
- Do we expose a "preview gradient angle" affordance with a draggable handle? (Tentative: defer to a follow-up polish issue.)
