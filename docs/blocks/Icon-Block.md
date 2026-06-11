# Icon Block

**Status:** v1.1 · shipped via parent issue #494 (Phases 1–7)
**Block name:** `artisanpack/icon`

The Icon Block lets authors drop branded SVG icons — Font Awesome Free or any custom set — into pages without touching markup. It ships Font Awesome 6 Free (Solid, Regular, Brands) out of the box and supports admin-imported custom sets such as a licensed Font Awesome Pro download.

---

## Block usage

Authors insert the block from the inserter under the **Design** category, then either pick a registered icon or paste a custom SVG.

### Picking a registered icon

1. Insert the **Icon** block.
2. The empty block renders a placeholder button — click it (or the **Choose icon…** button in the sidebar) to open the picker.
3. Search by name (e.g. `github`, `house`) or filter by set family (Solid / Regular / Brands / any registered custom set).
4. Click an icon to commit it. The block renders the inline SVG immediately.

### Pasting a custom SVG

When the catalog doesn't carry the icon you need, drop into **Inspector → Custom SVG** and paste any SVG markup. The server sanitizes the SVG before it reaches the canvas — script tags, event-handler attributes, and unsafe href schemes are stripped, and the cleaned markup is what persists. Pasting a custom SVG clears any previously-picked `iconRef` so the two render paths can't conflict.

### Styling

The block mirrors the WordPress reference Icon Block split: a dedicated **Icon color** field controls the SVG itself, while the standard WordPress controls handle the visual tile around it.

| Control | Where it shows up | Affected element |
|---------|------------------|------------------|
| **Icon color** | Sidebar → Color → Icon | SVG fill (applied as `color` on the body span; the bundled SVGs ship with `fill: currentcolor`) |
| **Background** | Sidebar → Color | Block wrapper — the visual "tile" behind the icon |
| **Border** (color, width, style, radius) | Sidebar → Border | Block wrapper |
| **Padding** | Sidebar → Dimensions | Block wrapper — space between the wrapper edge and the SVG |
| **Margin** | Sidebar → Dimensions | Block wrapper — block-flow spacing around the whole icon |

Palette colors (`Primary` / `Accent` / etc. picked from theme.json) and custom hex values are both supported. Palette picks resolve through the standard `has-{slug}-background-color` / `has-{slug}-border-color` classes against theme.json's CSS variables.

### Dimensions

The **Dimensions** panel ships three controls that update the canvas as you type — no blur required:

- **Size** — a single number input that sets both width and height (the default).
- **Width** — UnitControl override. Pixels, em, rem, percent, vw, or vh.
- **Height** — UnitControl override.

Width/height override the uniform `Size` per-axis. Clearing either field falls back to the size value, so the common "uniform-square icon" case stays a single slider.

### Link

The Inspector's link controls produce a wrapping `<a>` on the rendered icon. `target="_blank"` automatically forces `rel="noopener noreferrer"`; `rel="nofollow"` and `rel="sponsored"` are author-controlled.

### Accessibility

- **aria-label** — explicit accessible name; required when the icon is the only content in a link.
- **Decorative** — sets `aria-hidden="true"` and suppresses any `aria-label`. The editor shows a warning if a decorative icon sits inside a link without a wrapping label, because screen readers would announce an unlabeled link.

---

## Developer recipe — registering custom sets

Packages register additional icon sets through the existing [`ap.icons.register-icon-sets`](https://github.com/ArtisanPack-UI/icons) filter from `artisanpack-ui/icons`. The Icon Block picks them up automatically — no Icon Block-specific registration is needed.

```php
use ArtisanPackUI\Icons\Registries\IconSetRegistration;

// In your service provider's boot() method:
addFilter( 'ap.icons.register-icon-sets', function ( IconSetRegistration $registry ): IconSetRegistration {
    $registry->addSet(
        __DIR__ . '/../../resources/icons/myset',
        'myset',
    );
    return $registry;
} );
```

The registered set's icons render through the same `IconSvgResolver` pipeline as the bundled FA Free sets. If the picker's catalog also needs to surface them, register a matching catalog entry (typically by extending the manifest fed to `IconCatalog`).

---

## Admin upload walkthrough

Admins can also import custom sets directly from the editor UI — useful for shipping licensed icon packs (e.g. Font Awesome Pro) without adding them to the package source.

1. Open **Visual Editor → Icon sets** (admin-only; gated by the existing visual-editor management policy).
2. Click **Upload set**, supply a **prefix**, a human-readable **label**, and a **zip** of SVG files.
3. The server unpacks the zip, runs each SVG through `SvgSanitizer`, and persists the cleaned files to `storage/app/artisanpack/visual-editor/icons/{prefix}/`.
4. The set is registered through `ap.icons.register-icon-sets` on the next boot and immediately becomes selectable in the picker.

Non-SVG entries are listed in the upload report under `skipped`; SVGs that fail sanitization are listed under `failed`. A prefix collision (an existing bundled or uploaded set already using the same prefix) returns `409 Conflict` with the offending prefix surfaced in the error payload.

---

## Font Awesome Pro guidance — BYO SVGs

The block bundles Font Awesome 6 **Free** (Solid, Regular, Brands). It does **not** validate FA Pro kit codes, store FA tokens, or fetch Pro icons from a remote endpoint.

To use Font Awesome Pro:

1. Download the SVG bundle from your FA Pro account.
2. Pick the Pro families you want (e.g. Sharp Solid, Duotone) and bundle each into its own zip.
3. Upload each zip through the admin Icon Sets screen with a descriptive prefix (e.g. `fa-pro-sharp-solid`) and label.

Each Pro family is stored locally under `storage/app/artisanpack/visual-editor/icons/{prefix}/` and surfaces in the picker alongside Free. No tokens leave the host application.

---

## Server-side render

The block is dynamic — `IconBlock::render()` produces the final markup at request time:

```html
<div class="wp-block-artisanpack-icon"[ style="margin: 12px;"]>
    <span class="wp-block-artisanpack-icon__ref"
          data-icon-set="fab"
          data-icon-name="github"
          style="width: 48px; height: 48px; …">
        <svg width="100%" height="100%" viewBox="0 0 24 24">…</svg>
    </span>
</div>
```

Custom-SVG blocks emit the same wrapper with `__svg` in place of `__ref`, and the sanitized SVG inlined directly into the span.

If the referenced icon set has been deleted (or never registered), the renderer falls back to a placeholder span and keeps the `data-icon-set`/`data-icon-name` carriers so re-registering the set later restores the icon without re-authoring the post.

---

## Related

- Parent issue: [#494](https://github.com/ArtisanPack-UI/visual-editor/issues/494)
- Phase 7 (cross-phase tests + docs): [#558](https://github.com/ArtisanPack-UI/visual-editor/issues/558)
- Icon Sets API: `artisanpack-ui/icons` ([`ap.icons.register-icon-sets`](https://github.com/ArtisanPack-UI/icons))
