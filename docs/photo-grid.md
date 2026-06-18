# Photo Grid

The **Photo Grid** setting is a container-level inspector control that
forces every image-bearing descendant onto a uniform aspect ratio and
fill behaviour. It is available on `artisanpack/group`,
`artisanpack/columns`, and `artisanpack/grid`.

Photo Grid is orthogonal to layout. Authors still pick flex, grid, or
columns as they normally would; Photo Grid only normalises how the
images inside the container crop and fill their slots.

## Inspector UX

The setting lives in the block inspector under **Dimensions → Photo
Grid**:

- **Enable** toggle — turn the feature on for this container.
- **Aspect ratio** dropdown — 1:1, 4:3, 3:2, 16:9, 3:4, 9:16, "Inherit
  container" (do not force an aspect ratio; descendant images keep
  their natural height and stretch to fill the parent layout slot
  when the container provides one), or a **Custom…** option that
  reveals a `W/H` text input.
- **Object fit** — `cover` (default) or `contain`.
- **Object position** — 9-cell focal-point grid plus a numeric
  `x% y%` text input.

## Attribute shape

The block stores Photo Grid state as a single `photoGrid` attribute:

```jsonc
{
  "enabled": true,
  "aspectRatio": "1/1",        // or null for "inherit container"
  "objectFit": "cover",        // "cover" | "contain"
  "objectPosition": "50% 50%"  // any valid CSS object-position value
}
```

The renderer reads the same attribute server-side via
`PhotoGridSupport::wrapper()` and emits a `has-photo-grid` class plus
three CSS custom properties (`--ap-photo-grid-aspect`,
`--ap-photo-grid-fit`, `--ap-photo-grid-position`) on the container.

## Cascade behaviour

The fill rules apply to all image-bearing descendants at any depth —
`artisanpack/image`, `core/image`, `artisanpack/cover`, `core/cover`,
and raw `<img>` / `<picture>` markup. Authors do not need to re-enable
the setting on nested containers; the innermost Photo Grid container
wins (CSS variables resolve on the nearest ancestor that defined them).

Non-image children (paragraphs, buttons, headings) inside the
container are unaffected.

## theme.json defaults

Themes can configure defaults for new Photo Grid containers and / or
disable the inspector sub-section entirely via `theme.json`:

```jsonc
{
  "settings": {
    "artisanpack": {
      "photoGrid": {
        "enable": true,                 // false hides the inspector
        "defaultAspectRatio": "4/3",
        "defaultObjectFit": "cover",
        "defaultObjectPosition": "50% 30%"
      }
    }
  }
}
```

When `enable` is `false`, the Photo Grid sub-section is hidden in the
inspector, but already-saved containers that have Photo Grid turned on
continue to render normally — the renderer is independent of the
inspector visibility flag.

## Renderer integration

Server-side rendering is handled by
`ArtisanPackUI\VisualEditorRendererBlade\Support\PhotoGridSupport`.
Each container partial calls:

```php
$photoGridClasses = PhotoGridSupport::wrapperForBlock( $attributes );
```

…and merges the returned classes into its wrapper class list. The CSS
custom properties are emitted as a scoped inline `<style>` rule via
the existing `ResponsiveCssAccumulator`, so identical configs across
multiple block instances collapse to one rule set in the consolidated
output.

The companion stylesheet
(`packages/visual-editor-renderer-blade/resources/assets/frontend/photo-grid.css`)
ships via the standard `<x-ve-blocks-styles />` component and targets
the descendants with `.has-photo-grid :is(img, picture, …)` selectors.

## Edge cases

- **Empty container** — toggle is allowed, no visible effect until
  images are added.
- **Custom ratio invalid input** — the inspector flags a help message
  in the text input; the renderer treats invalid values the same as
  `null` (no `aspect-ratio` stamped).
- **Photo Grid disabled mid-edit** — clearing the toggle removes the
  class + variables; images revert to their natural size.
- **`object-position` picker** — the 9-cell radio group and the
  numeric input stay in sync; selecting a cell updates the text value,
  and editing the text updates the radio selection on the next render.
