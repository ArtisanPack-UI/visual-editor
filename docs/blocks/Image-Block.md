# Image Block

**Status:** v1.11 · width control shipped via #790
**Block name:** `artisanpack/image`

The Image block is a fork of `core/image` (upstream pinned at `@wordpress/block-library` 9.43.0). It matches upstream's stored markup byte-for-byte and adds a first-party **width control** — presets, a custom width input with a px/% unit toggle, and drag-to-resize handles — so authors can size images without hand-editing style attributes.

---

## Width control (v1.11)

The width control lives in **Inspector → Settings → Image dimensions** and is also available as drag-to-resize handles on the image itself. All three surfaces write the same block attributes so the front-end render is a single code path.

### Presets

Four preset percentages map to the container width:

| Preset | Percentage | Stored `width` |
|--------|-----------:|----------------|
| **S**  | 25% | `25%` |
| **M**  | 50% | `50%` |
| **L**  | 75% | `75%` |
| **Full** | 100% | `100%` |

Presets are proportional — they clear any explicit `height` so the browser derives it from the natural ratio (or the block's `aspectRatio` when set).

Real-world case: dropping a wordmark into a header ends up squeezed horizontally in a full-width column. Picking **Small** or **Medium** shrinks the image to that fraction of the container while keeping the ratio, without touching the header layout.

### Custom width

The numeric input + unit toggle writes to the `width` attribute directly. Units are `px` (absolute) or `%` (relative to the container). The unit toggle state is remembered across editor reloads via the `widthUnit` attribute.

Clearing the input resets both `width` and `height` — the image renders at its intrinsic size again.

### Drag-to-resize

When the image is selected, four resize handles appear on the corners and side edges. Dragging:

- Writes to `width` on release (never mid-drag, so undo history stays clean).
- Uses the current `widthUnit`. On `%`, the resulting percentage is calculated against the wrapper's on-screen width.
- **Snaps to the nearest preset** when within 2% of a preset value, so a hand-dragged "about 50%" lands cleanly on `50%`.
- Locks the aspect ratio when **Keep aspect ratio** is on (the default). Turning it off releases the constraint and lets height be set independently via other dimension controls.

### Reset

The **Reset** button next to the presets clears `width`, `widthUnit`, and any dependent `height` so the image renders at its intrinsic size.

---

## Attributes

The block ships the full upstream `core/image` attribute schema plus these fork-specific additions:

| Attribute | Type | Default | Notes |
|-----------|------|---------|-------|
| `widthUnit` | `'px' \| '%'` | *(none)* | Remembers the last selected unit toggle state. Not read by `save.tsx` — `width` already carries the unit. |
| `keepAspectRatio` | `boolean` | `true` | When `true`, drag-to-resize locks the ratio and preset picks clear any explicit `height`. |

The upstream `width`, `height`, `aspectRatio`, `sizeSlug`, `scale`, and `focalPoint` attributes are unchanged — they serialize via `save.tsx` exactly as upstream does.

---

## Front-end render

`save.tsx` inlines `width` on the `<img>` `style` attribute — same as upstream. No CSS is required from the front end for the width control to take effect. The `is-resized` class is applied to the `<figure>` whenever `width` or `height` is set, matching upstream's convention.

Pasted `core/image` markup with an existing inline `width` (e.g. `style="width: 300px"`) round-trips into the fork's `width` attribute via the existing raw + block transforms — width control state loads and re-saves without drift.

---

## Divergence from upstream

Upstream `core/image` powers width via `DimensionsTool` and `ResizableBox` from `@wordpress/block-library/private-apis`, which is unreachable from outside `@wordpress/block-library` (blocked by the package's `exports` field). The fork implements width control from scratch on top of the public `@wordpress/components` `ResizableBox`. See `resources/js/visual-editor/blocks/image/upstream-state.json` for the full extension record.
