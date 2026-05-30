# Visual Editor — State Design Tools

**Package:** `artisanpack-ui/visual-editor`
**Version Target:** 1.0.0
**Created:** 2026-05-30
**Status:** Planning
**Related:**
 - [`06-global-styles.md`](06-global-styles.md) — token system the state styles emit against
 - [`17-responsive-design-tools.md`](17-responsive-design-tools.md) — sister feature, shares the value-resolver layer
 - [`11-v1-expansion.md`](11-v1-expansion.md) — V1 scope this widens

---

## 1. Problem Statement

Block style attributes resolve to a single value rendered in the default (idle) state. There's no first-class way for an editor to say "this button's background is `accent` normally but `accent-700` on hover" without writing custom CSS. Buttons, links, and any block with an interactive feel ship visually flat as a result.

## 2. Target User

Content editors who want pressable, interactive feedback on hover/focus/active states without leaving the editor, and developers who want a structured place to author state styles that survives theme switches and exports.

## 3. User Stories

- As an editor, I want to set a different background color, text color, border, shadow, and transform for a block when the user hovers it.
- As an editor, I want the same control for `focus` (and `focus-visible`) so links and buttons get correct keyboard-accessibility treatment.
- As an editor, I want a state switcher in the InspectorControls that scopes my next edit to the selected state (idle / hover / focus / focus-visible / active / disabled).
- As an editor, I want a "preview state" affordance that simulates the hover/focus look on the canvas without me having to actually hover with the mouse.
- As a developer, I want the state styles emitted as standard `:hover` / `:focus-visible` etc. selectors so they work in the published page without JS runtime.
- As a developer, I want to add custom states (e.g. `aria-current`) through config so design-system custom states are first-class.

## 4. Scope

### 4.1 In scope (v1.0)

- **State registry** with built-in states: `idle` (the base), `hover`, `focus`, `focus-visible`, `active`, `disabled`. Each state has:
  - a stable key (`hover`)
  - a CSS pseudo-selector or selector list (`&:hover`)
  - a display label and an icon
  - a `inheritsFrom` chain for resolution (e.g. `active` inherits from `hover` inherits from `idle`)
- **State registry overrides** via `config/artisanpack/visual-editor.php` (`states`) and `theme.json` → `settings.custom.artisanpack.states`. Same priority chain as breakpoints: `theme.json → config → defaults`. Custom states (e.g. `aria-current`, `[data-active="true"]`) are additive.
- **Per-state attribute storage** on every supported attribute, mirroring the responsive shape:
  ```json
  {
    "idle":  "var(--ap-color-accent)",
    "hover": "var(--ap-color-accent-700)",
    "focus-visible": null
  }
  ```
  `null` falls back through the `inheritsFrom` chain.
- **State switcher** in the InspectorControls header (icon strip), defaulting to `idle`. The active state scopes which slot in the attribute storage the controls read/write.
- **Canvas "preview state"** affordance — a toolbar toggle that adds a temporary attribute (`data-ap-preview-state="hover"`) to the selected block and a corresponding CSS rule so editors see the hover look without simulating pointer events. The toggle is editor-only and never reaches saved content.
- **Supported attributes**: `color.background`, `color.text`, `color.gradient`, `border.color`, `border.width`, `border.style`, `border.radius`, `shadow`, `typography.textDecoration`, `dimensions.transform` (scale/translate), `transition` (single composite control). Spacing is **not** state-scoped in v1.0.
- **Block support opt-in** via `supports.artisanpackStates` in `block.json`. All forked interactive blocks (`button`, `buttons`, `navigation`, `image` (when linked), `cover`, `query-pagination`, `media-text`, `details`, plus the `core/link` element style) opt in by default.
- **CSS emission** — server-side renderer wraps state styles in a unique class scope: `.ap-block-<uid>:hover { ... }` etc. Where the value maps to a Tailwind token, the renderer prefers a generated class string (`hover:bg-accent-700`); otherwise it emits scoped inline styles.
- **`transition` control** — a single field that emits `transition-property`, `transition-duration`, and `transition-timing-function` together. Default `all 150ms ease`.

### 4.2 Out of scope

- **Per-breakpoint state styles** (e.g. hover-only-on-desktop). Future composition with [`17-responsive-design-tools.md`](17-responsive-design-tools.md); explicitly deferred to v1.x.
- **`:has()` / parent-state selectors.** Defer pending broader browser support and a use-case.
- **Group-level "all children get this hover"** style inheritance. Defer to v1.x.
- **Animation timing curves** beyond the default + a fixed dropdown of common easings. Custom cubic-béziers can be authored in the next animations feature.
- **Spacing state styles.** Hover-grows-the-padding is a niche pattern; deferred until requested.

## 5. Behavior

### 5.1 Happy path

1. Editor selects a Button block.
2. In InspectorControls, they switch the state strip to `hover`.
3. They change `color.background` to `accent-700` and `dimensions.transform` to `scale(1.02)`. Storage becomes `{ idle: 'accent', hover: 'accent-700' }` for the background and similar for the transform.
4. They click the preview-state toggle to `hover`; the canvas shows the hovered look.
5. They save. The server emits a class scope with `.ap-block-{uid}:hover { background-color: var(--ap-color-accent-700); transform: scale(1.02); transition: all 150ms ease; }` (or the Tailwind class equivalent).
6. The live page reflects the hover behavior with pure CSS — no editor runtime required.

### 5.2 Edge cases

- **State is registered in a custom theme but a block doesn't opt in.** Inspector still shows the state in the strip (greyed) with a tooltip explaining the block doesn't support state styling.
- **Inheritance chain has a cycle.** Schema validator rejects the config at load time with a descriptive error.
- **`focus-visible` is the only override.** Resolver falls back through `focus → idle` for browsers without `:focus-visible` support (handled by an `@supports selector(:focus-visible)` rule).
- **Hover is set on a touch-only device.** The CSS already handles this via the standard `(hover: hover)` media query — emitted automatically when the renderer detects a hover-state override.
- **A custom state's selector is invalid CSS.** Schema validator rejects with a descriptive error.

## 6. Acceptance Criteria

- [ ] Built-in states (`hover`, `focus`, `focus-visible`, `active`, `disabled`) work out of the box on every opted-in block.
- [ ] Adding a custom state in `theme.json` makes it appear in the state strip and emit the configured selector.
- [ ] Inheritance chain works: `active` falls back to `hover` falls back to `idle` when slots are `null`.
- [ ] State switcher scopes attribute reads/writes; toggling away preserves the previous state's values.
- [ ] Preview-state toggle visually simulates the state on the canvas and never persists into saved content.
- [ ] Server-side renderer emits Tailwind classes when the value maps to a token, scoped inline styles otherwise.
- [ ] `@media (hover: hover)` wrapping is applied to `:hover` styles automatically.
- [ ] Forked interactive blocks (`button`, `buttons`, `navigation`, `image`, `cover`, `media-text`, `details`, `query-pagination`) opt in by default.
- [ ] Pest tests cover: state registry resolution, inheritance chain, schema validation, attribute storage migration.
- [ ] Playwright E2E covers: state switching in InspectorControls, preview-state toggle, save+reload persistence, live page hover behavior.
- [ ] Docs in `docs/state-design-tools.md` cover registering custom states.

## 7. Implementation Notes

### 7.1 Files to create

- `src/States/StateRegistry.php` — resolves the merged state registry from theme/config/defaults.
- `src/States/StateValueResolver.php` — given an attribute object + active state, returns the resolved scalar via inheritance chain.
- `src/States/InheritanceChainValidator.php` — cycle detection.
- `resources/js/visual-editor/states/StateSwitcher.tsx` — InspectorControls state strip.
- `resources/js/visual-editor/states/useStateValue.ts` — JS resolver hook.
- `resources/js/visual-editor/states/PreviewStateToggle.tsx` — toolbar component.
- `resources/js/visual-editor/states/StateControl.tsx` — InspectorControls wrapper.
- `packages/visual-editor-renderer-{blade,react,vue}/src/states/` — renderer emission per stack.
- `tests/Unit/States/*` and `tests/Feature/States/RendererTest.php`.

### 7.2 Files to modify

- `config/visual-editor.php` — add the `states` key with documented defaults.
- `src/VisualEditorServiceProvider.php` — register `StateRegistry` singleton.
- All forked interactive `block.json` files — add `supports.artisanpackStates`.
- `resources/js/visual-editor/blocks/_shared/inspector-controls/*` — extend color/border/shadow/transform controls to use `StateControl`.
- `docs/theming.md` — document the state registry hierarchy.

### 7.3 Database / schema

No DB migrations.

### 7.4 Dependencies

None new.

## 8. Open Questions

- Do we ship a `visited` state for `core/link`-style elements out of the box? (Tentative: yes, but `idle`-only by default unless explicitly opted in by the block.)
- Should the preview-state toggle support multi-state preview (e.g. `hover + focus-visible` simultaneously)? (Tentative: defer.)
