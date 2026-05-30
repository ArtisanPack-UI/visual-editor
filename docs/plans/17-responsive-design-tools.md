# Visual Editor — Responsive Design Tools

**Package:** `artisanpack-ui/visual-editor`
**Version Target:** 1.0.0
**Created:** 2026-05-30
**Status:** Planning
**Related:**
 - [`06-global-styles.md`](06-global-styles.md) — token system the breakpoint config piggybacks on
 - [`11-v1-expansion.md`](11-v1-expansion.md) — V1 scope this widens
 - [`18-state-design-tools.md`](18-state-design-tools.md) — sister feature, shares the value-resolver layer

---

## 1. Problem Statement

Block style and structural settings (e.g. `columns.count`, padding, font size, alignment) currently resolve to a single value that applies across every viewport. Editors who want a 3-column layout on desktop and a stacked layout on mobile have to author two trees, hack the markup, or skip the block entirely. The editor has Tailwind in the stack but no first-class way to author per-breakpoint overrides through the UI.

## 2. Target User

Content editors and developers building responsive layouts who want desktop/tablet/mobile-specific styles **without** dropping into custom CSS, and developers who want to define which breakpoints exist for their app/theme.

## 3. User Stories

- As an editor, I want to set the number of columns differently on mobile, tablet, and desktop so the layout reads well on every device.
- As an editor, I want padding/margin/font-size/alignment/visibility on any block to vary per breakpoint, with the desktop value cascading down unless I override it at a smaller size.
- As an editor, I want a viewport switcher in the editor toolbar that shows the canvas at the active breakpoint **and** scopes which breakpoint my style edits apply to.
- As a developer, I want to add, rename, or remove breakpoints through `config/artisanpack/visual-editor.php` or a theme's `theme.json` so my app can match its own design system, not Tailwind defaults.
- As a developer, I want the breakpoints I configure to be the same ones Tailwind compiles against, so editor previews and production rendering match.

## 4. Scope

### 4.1 In scope (v1.0)

- **Breakpoint registry** resolved in this priority order (highest wins):
  1. Active theme's `theme.json` → `settings.custom.artisanpack.breakpoints`
  2. Application's `config('artisanpack.visual-editor.breakpoints')`
  3. Package defaults (Tailwind v4 defaults: `sm: 640px`, `md: 768px`, `lg: 1024px`, `xl: 1280px`, `2xl: 1536px`)
- Breakpoints can be **added** (e.g. `3xl`), **removed**, or **resized** at any layer. The resolver merges by key.
- **Mobile-first cascade semantics** — a base (unprefixed) value applies everywhere; each named breakpoint override applies at that min-width and up, mirroring Tailwind's `sm:` / `md:` modifiers exactly. The resolver returns the value of the largest matching breakpoint or the base if no breakpoint matches.
- **Per-breakpoint attribute storage** on every supported attribute, stored as a discriminated object:
  ```json
  {
    "base": 4,
    "sm": null,
    "md": 6,
    "lg": 8,
    "xl": null
  }
  ```
  `null` means "inherit from the next smaller defined value." Plain scalars remain valid for unmodified blocks and migrate lazily.
- **Viewport switcher** in the editor toolbar (mobile / tablet / desktop / wide / full, plus any custom breakpoints) that:
  - resizes the canvas iframe to the breakpoint's min-width,
  - sets an editor-state `activeBreakpoint` that the InspectorControls read and write into,
  - shows a "Reset to base" affordance per attribute.
- **Block support opt-in** declared per block in `block.json` via a new `supports.artisanpackResponsive` key listing which attribute paths are breakpoint-aware. Out of the gate, all `artisanpack/*` forks opt in their layout-affecting attributes: `spacing`, `typography.fontSize`, `align`, `dimensions`, `columns.count` (on `artisanpack/columns`).
- **CSS emission** — server-side renderer (Blade) outputs Tailwind-class strings using the registered prefixes (`md:px-6`, etc.) when the value maps to a token, and inline CSS via `@media (min-width: …)` queries when it's a custom value. React/Vue renderers do the same client-side.
- **JS resolver helper** (`useResponsiveValue(attribute, breakpoints)`) for blocks whose edit UI needs the resolved value at the active editor breakpoint.
- **Schema validation** — bad breakpoint configs (invalid CSS lengths, non-unique values, missing required keys when present) fail loudly with a descriptive error in `php artisan vendor:publish --tag=visual-editor-config` and on theme load.

### 4.2 Out of scope

- **Container queries.** Considered for v1.x once browser support and tooling settle and the value-resolver layer here has a real production track record.
- **Per-breakpoint block visibility.** Handled by the contextual visibility feature ([`21-block-visibility-contextual.md`](21-block-visibility-contextual.md)) which has its own UI surface.
- **Per-breakpoint state styles** (e.g. hover-on-mobile-only). Future composition of this feature with [`18-state-design-tools.md`](18-state-design-tools.md); explicitly deferred to v1.x.
- **Desktop-first or independent (non-cascading) modes.** Mobile-first only for v1.0.
- **Auto-conversion of existing scalar attributes** beyond lazy promotion on first override. No bulk migration tool.

## 5. Behavior

### 5.1 Happy path

1. Editor selects a Columns block.
2. They toggle the viewport switcher to **mobile**. The canvas resizes to `sm` (640px).
3. In the InspectorControls, they set `count = 1`. The attribute storage becomes `{ base: 3, sm: 1 }`.
4. They switch to **tablet** (`md`). The resolver shows `1` (cascaded from `sm`).
5. They set `count = 2`. Storage becomes `{ base: 3, sm: 1, md: 2 }`.
6. They preview the page. Server-side rendering emits a `grid-cols-3 sm:grid-cols-1 md:grid-cols-2` class string. Live page reflects all three layouts as the viewport resizes.

### 5.2 Edge cases

- **Theme adds a custom breakpoint after content is authored.** Existing attribute objects don't have the new key. The resolver treats missing keys as `null` (inherit), so nothing breaks.
- **Theme removes a breakpoint the content uses.** Stored values for the removed key are preserved on save but ignored at render time; a `php artisan visual-editor:audit-breakpoints` command surfaces orphaned overrides.
- **Custom value can't be expressed as a Tailwind class.** Renderer falls back to inline `@media` rules in a per-block `<style>` tag scoped by a generated class.
- **Editor user is offline or the viewport switcher hasn't been touched.** `activeBreakpoint` defaults to `base`; edits go to the base value as today.
- **Block doesn't declare `supports.artisanpackResponsive` for an attribute.** Inspector shows the single-value control with a tooltip explaining how to enable per-breakpoint values (links to docs).

## 6. Acceptance Criteria

- [ ] Breakpoint registry resolves in the documented `theme.json → config → defaults` order with merge-by-key semantics.
- [ ] Adding a new breakpoint (`3xl`) via `theme.json` makes it appear in the viewport switcher and as a control row in supported attributes' InspectorControls.
- [ ] Removing a breakpoint via config drops it from the UI; orphaned stored values are preserved on save and surfaced by `visual-editor:audit-breakpoints`.
- [ ] Setting `columns.count = 1` at `sm` and `count = 2` at `md` renders the correct Tailwind class string on the server.
- [ ] The base value cascades up through breakpoints that are `null`; a value set at `md` is visible at `lg` and `xl` unless overridden.
- [ ] Viewport switcher resizes the canvas and scopes new edits to the active breakpoint.
- [ ] Lazy migration: a block with a scalar `fontSize` attribute opens in the editor without errors and promotes to the discriminated-object form only when an override is set.
- [ ] All forked layout-affecting blocks (`group`, `columns`, `column`, `buttons`, `spacer`, `cover`, `media-text`) opt into responsive support for at least `spacing`, `align`, and where applicable `columns.count` / `flex-direction`.
- [ ] Theme/config schema validation fails loudly on invalid lengths, duplicate values, and unknown keys with line numbers in the error message.
- [ ] Feature is documented in `docs/responsive-design-tools.md` with examples for editors and developers.
- [ ] Pest tests cover: resolver cascade, breakpoint registry merging, schema validation, block.json opt-in detection, server renderer Tailwind-class emission, server renderer custom-value `@media` emission.
- [ ] Playwright E2E covers: viewport switching, per-breakpoint editing on a Columns block, value persistence across save+reload.

## 7. Implementation Notes

### 7.1 Files to create

- `src/Responsive/BreakpointRegistry.php` — resolves the merged registry from theme/config/defaults; exposes `all()`, `get(string $key)`, `prefixes()`, `validate(array $raw)`.
- `src/Responsive/ResponsiveValueResolver.php` — given an attribute object + active breakpoint, returns the resolved scalar.
- `src/Responsive/AttributeMigrator.php` — promotes a scalar attribute to the discriminated-object form on first override.
- `src/Console/AuditBreakpointsCommand.php` — `visual-editor:audit-breakpoints` artisan command.
- `resources/js/visual-editor/responsive/useResponsiveValue.ts` — JS resolver hook for block edit components.
- `resources/js/visual-editor/responsive/ViewportSwitcher.tsx` — toolbar component.
- `resources/js/visual-editor/responsive/ResponsiveControl.tsx` — wrapper used by InspectorControls to render per-breakpoint UIs.
- `packages/visual-editor-renderer-blade/src/ResponsiveClassResolver.php` — Blade-side class string emission.
- `packages/visual-editor-renderer-react/src/responsive/` — same role as Blade for React renderer.
- `packages/visual-editor-renderer-vue/src/responsive/` — same role as Blade for Vue renderer.
- `tests/Unit/Responsive/*` — resolver, registry, migrator, schema validator.
- `tests/Feature/Responsive/RendererTest.php` — end-to-end render assertions.

### 7.2 Files to modify

- `config/visual-editor.php` — add the `breakpoints` key with documented defaults.
- `src/VisualEditorServiceProvider.php` — register `BreakpointRegistry` singleton, bind resolver, register console command.
- All `artisanpack/*` block `block.json` files that should opt in — add `supports.artisanpackResponsive`.
- `resources/js/visual-editor/blocks/columns/edit.tsx` — wire `count` through `ResponsiveControl`.
- `resources/js/visual-editor/blocks/_shared/inspector-controls/*` — extend spacing/typography/align controls to render `ResponsiveControl` when the attribute opts in.
- `docs/theming.md` and `docs/dev-setup.md` — document the breakpoint registry hierarchy.

### 7.3 Database / schema

No DB migrations. Breakpoint registry is a runtime concern; attribute storage stays in the existing block-tree JSON column.

### 7.4 Dependencies

None new. Tailwind v4 is already in the stack; the resolver emits class strings the existing build picks up.

## 8. Open Questions

- Do we want to bake in a `print` pseudo-breakpoint at v1.0, or defer to a follow-up? (Tentative: defer.)
- Should `useResponsiveValue` also be exposed to host apps via `@artisanpack-ui/visual-editor/react`? (Tentative: yes, behind an explicit export.)
