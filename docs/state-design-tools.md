# State Design Tools

**Status:** v1.0
**Plan:** [`plans/18-state-design-tools.md`](plans/18-state-design-tools.md)
**Issue:** #488

The editor lets editors author different styles for the same block at different interactive states — hover, focus, focus-visible, active, disabled — and lets developers register additional custom states (e.g. `aria-current`) without writing CSS. The same registry that drives the editor UI also drives the server-side renderer, so previews match production exactly. Output is plain CSS — no JS runtime is required on the published page.

This document covers both the editor-facing workflow and the developer integration surface. See also:

- [`theming.md`](theming.md) — for the broader theme.json hierarchy (where overrides live).
- [`responsive-design-tools.md`](responsive-design-tools.md) — sister feature with the same value-resolver shape.

---

## 1. For editors

### 1.1 The state switcher

When you select a block that opts into state styling, the Inspector's Block tab shows a strip of state chips at the top of the panel:

```text
[ Idle ] [ Hover ] [ Focus ] [ Focus visible ] [ Active ] [ Disabled ]
```

The strip is **block-specific** — it appears for buttons, image, cover, media-text, details, and any block that declares `supports.artisanpackStates`. Blocks that don't opt in show a short explanatory message instead.

Selecting a chip scopes the next style edit to that state. The currently selected chip is highlighted, and chips for states that already have an override show a small dot.

### 1.2 Setting a per-state override

1. Select the block you want to customize.
2. Click the state chip for the state you want to target (e.g. **Hover**).
3. Adjust any supported control in the Inspector — background color, text color, border, shadow, transform, transition.

The value you set applies in that state's CSS selector context only. States that aren't explicitly set inherit through the chain (see §1.4).

### 1.3 Previewing a state on the canvas

When a non-idle state is selected, a **Preview** button appears next to the chip strip. Clicking it simulates the state on the canvas without you having to actually hover or focus the block — handy for inspecting `:focus-visible` styles in particular.

Click **Stop preview** to return to the default view. The preview is editor-only and never reaches the saved content.

### 1.4 Inheritance chain

States fall back through a chain so you only need to set the slots that genuinely differ:

| State            | Falls back to |
| ---------------- | ------------- |
| `hover`          | `idle`        |
| `focus`          | `idle`        |
| `focus-visible`  | `focus` → `idle` |
| `active`         | `hover` → `idle` |
| `disabled`       | `idle`        |

If you set `hover` to `accent-700`, `active` inherits `accent-700` automatically unless you override it. Clearing an override sets the slot back to inheriting through the chain.

### 1.5 Resetting an override

Each per-state control surfaces a **Reset {state}** button when an override is currently set at the active state. Clicking it removes that single override and lets the value cascade from the next link in the inheritance chain.

If clearing the override leaves no other state overrides, the stored attribute collapses back to its scalar form — no extra JSON on disk.

---

## 2. For developers

### 2.1 Configuring states

States resolve in priority order — highest layer wins on key collision:

1. **Active theme's `theme.json`** — `settings.custom.artisanpack.states`
2. **Application config** — `artisanpack.visual-editor.states`
3. **Package defaults** — `StateRegistry::DEFAULTS` (idle, hover, focus, focus-visible, active, disabled)

#### Config example

```php
// config/artisanpack/visual-editor.php
return [
    'states' => [
        // Add a new aria-current state:
        'aria-current' => [
            'label'        => 'Current page',
            'selector'     => '&[aria-current="page"]',
            'icon'         => 'flag',
            'inheritsFrom' => 'idle',
        ],
        // Remove a built-in state (rare):
        'disabled' => null,
    ],
];
```

#### theme.json example

```json
{
    "settings": {
        "custom": {
            "artisanpack": {
                "states": {
                    "aria-current": {
                        "label": "Current page",
                        "selector": "&[aria-current=\"page\"]",
                        "inheritsFrom": "idle"
                    }
                }
            }
        }
    }
}
```

### 2.2 State definition shape

Each state entry is an associative array:

| Key              | Type    | Required | Description |
| ---------------- | ------- | -------- | ----------- |
| `label`          | string  | yes      | Human-readable label shown in the Inspector chip. |
| `selector`       | string  | yes\*    | CSS pseudo or attribute selector. `&` is replaced with the block's unique scope at emit time. The reserved `idle` state must use `''`. |
| `icon`           | string  | no       | Optional icon slug for the Inspector chip. |
| `inheritsFrom`   | string  | no       | Parent state key for null-fallback. The `idle` slot is the implicit root. |
| `hoverMediaWrap` | bool    | no       | When `true`, the renderer wraps the rule in `@media (hover: hover)`. Defaults to `false`. The built-in `hover` state has this enabled. |

\* `idle` must have an empty selector — it represents the default styles.

### 2.3 Block opt-in

A block opts into state styling by declaring `supports.artisanpackStates` in its `block.json`:

```json
{
    "supports": {
        "artisanpackStates": {
            "attributes": [
                "color.background",
                "color.text",
                "border.color",
                "border.radius",
                "shadow",
                "dimensions.transform",
                "transition"
            ]
        }
    }
}
```

`attributes` is an allow-list of which attribute paths support per-state overrides. Listing fewer paths is the recommended way to scope state styling to the attributes a block actually wants to expose.

These blocks opt in by default:

- `artisanpack/button`
- `artisanpack/buttons`
- `artisanpack/image`
- `artisanpack/cover`
- `artisanpack/media-text`
- `artisanpack/details`

`artisanpack/navigation` is intentionally not opted in for v1.0 — the parent block doesn't declare color/border supports, so state styling lands more naturally on individual `core/navigation-link` children. That's deferred to a follow-up.

### 2.4 Attribute storage shape

State-capable attributes are stored as either:

- a scalar (legacy / unmodified content), or
- a stateful `{ idle, hover, focus, … }` object once any per-state override is set:

```json
{
    "idle":  "var(--ap-color-accent)",
    "hover": "var(--ap-color-accent-700)",
    "focus-visible": null
}
```

`null` means "inherit from the next link in the chain." The editor promotes a scalar to the discriminated form on first override and demotes it back to a scalar when the last override is cleared, keeping saved JSON compact.

### 2.5 CSS emission

The server-side `StateCssEmitter` turns a block's stateful attributes into scoped CSS. Given a unique class scope and a map of `property => stateful value`, it emits:

- An `idle` rule against the scope.
- One rule per non-idle state whose resolved value differs from its inheritance parent (the renderer skips redundant rules).
- A wrapping `@media (hover: hover) { … }` around any rule whose state has `hoverMediaWrap = true`.
- A default `transition: all 150ms ease;` on the `idle` rule whenever any non-idle state is set and no explicit `transition` was authored.

Example output:

```css
.ap-block-abc123 { background-color: var(--ap-color-accent); transition: all 150ms ease; }
@media (hover: hover) { .ap-block-abc123:hover { background-color: var(--ap-color-accent-700); } }
.ap-block-abc123:focus-visible { background-color: var(--ap-color-accent-500); }
```

### 2.6 Supported attributes

State overrides are supported on:

- `color.background`, `color.text`, `color.gradient`
- `border.color`, `border.width`, `border.style`, `border.radius`
- `shadow`
- `typography.textDecoration`
- `dimensions.transform` (scale / translate)
- `transition`

Spacing is intentionally not state-scoped in v1.0 (hover-grows-the-padding is a niche pattern). Per-breakpoint state styles are also out of scope for v1.0 — that composition is on the v1.x roadmap.

### 2.7 Validating a custom state

The registry validates every state at construction time. Bad configuration raises a descriptive `InvalidArgumentException`. Common pitfalls:

- The reserved `idle` slot is missing or carries a non-empty selector.
- A non-idle state has no `selector` or no `label`.
- `inheritsFrom` points at a state that isn't registered.
- The inheritance chain forms a cycle.

The `InheritanceChainValidator` can also run independently against a candidate state map — useful for CI linting of `theme.json`.

---

## 3. Reference

- PHP: `ArtisanPackUI\VisualEditor\States\StateRegistry`, `StateValueResolver`, `StateAttributeMigrator`, `StateCssEmitter`, `InheritanceChainValidator`.
- JS: `@artisanpack-ui/visual-editor` → `StateRegistry`, `resolveStateValue`, `useStateValue`, `StateSwitcher`, `StateControl`, `PreviewStateToggle`.
- Base key constant: `idle` (PHP `StateRegistry::BASE_KEY`, JS `BASE_KEY`).
