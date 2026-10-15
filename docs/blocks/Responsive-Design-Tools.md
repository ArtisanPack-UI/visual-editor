# Responsive Design Tools

**Status:** v1.0 — desktop-first since 1.12.1

The editor lets editors and developers author per-breakpoint style and structural overrides without hand-editing CSS. The same registry that drives the editor UI also drives the server-side renderer, so previews match production exactly.

This document covers both the editor-facing workflow and the developer integration surface. See also:

- [Theming](../post-editor/Theming.md) — for the breakpoint registry hierarchy (where overrides live).
- [Configuration](../Configuration.md#breakpoints) — for the host-app config override path.

---

## 1. For editors

### 1.1 The viewport switcher

Since 1.12.1 the responsive model is **desktop-first**. The editor toolbar shows an `All sizes` button for the unprefixed base value (the desktop design), followed by one button per desktop-first device breakpoint, largest first:

```
[ All sizes ] [ Tablet ] [ Mobile ]
```

Selecting a button does two things atomically (#617):

1. **Resizes the canvas** to the breakpoint's `previewWidthPx` so you can preview the layout at that device size. `All sizes` clears the width constraint and the canvas fills the available editor area.
2. **Scopes the next style edit** to that breakpoint. Any Inspector control that supports responsive overrides records your change at the active breakpoint.

The `All sizes` button writes to the unprefixed base value — the desktop design, which applies at every width unless a Tablet or Mobile override matches.

The two shipped device breakpoints are `Tablet` (`tablet`, applies at 1023px and below, 768px canvas) and `Mobile` (`mobile`, applies at 767px and below, 375px canvas). Each preview width sits inside its breakpoint's range, so the canvas shows exactly what a visitor on that device sees. The button tooltips and the Inspector's scope chip read "and below" / "Editing at Mobile and down" to match.

The pre-1.12.1 mobile-first keys (`sm`, `md`, `lg`, `xl`, `2xl`) are no longer offered in the switcher. They stay registered as legacy entries, so content saved with them keeps rendering exactly as before (see [§2.7](#27-legacy-mobile-first-content)).

The same switcher drives the post editor, the site editor (templates, template parts), and the patterns editor.

### 1.2 Setting a per-breakpoint override

1. Select the block you want to customize.
2. Click the viewport switcher button for the breakpoint you want to target (e.g. **Mobile**).
3. Adjust the control (padding, font size, column count, alignment, …) in the Inspector.

The value you just set applies at that breakpoint and below. A Tablet value also applies on phones unless you set a Mobile override.

### 1.3 Resetting an override

Each per-breakpoint control surfaces a **Reset to base** button when an override is currently set at the active breakpoint. Clicking it removes that single override and lets the value cascade through from the next-larger defined slot (Tablet, then base).

If clearing the override leaves no other overrides, the stored attribute collapses back to the simple scalar form — no extra JSON on disk.

### 1.4 Desktop-first cascade

Since 1.12.1 the cascade is desktop-first:

- `base` is the desktop design and applies everywhere unless overridden.
- A value set at `tablet` applies at 1023px and below (`@media (max-width:1023px)`), unless `mobile` overrides it.
- A value set at `mobile` applies at 767px and below (`@media (max-width:767px)`) and does **not** affect tablet or desktop widths.
- Mobile inherits from Tablet, then from base.

Rich-text edits (paragraph and heading content, button labels, …) made while Tablet or Mobile is active are kept. Before 1.12.1 they were silently dropped.

Only the attribute paths a block opts into (see [§2.2](#22-opting-a-block-into-responsive-support)) are stored per breakpoint. Any other style edit made while previewing Tablet or Mobile (border color or width, line height, letter spacing, shadow, …) changes the base value. Since 1.15.0 such an edit keeps the block's other base `style` values, and clearing a base value at a breakpoint clears it; before, the edit could wipe the block's other base styles.

#### Preset and custom pairs

Font size, text color and background color can each be stored two ways: a preset slug (`fontSize`, `textColor`, `backgroundColor`) or a custom value (`style.typography.fontSize`, `style.color.text`, `style.color.background`). Since 1.15.0 (#887), setting one half of a pair at a breakpoint stores a `false` marker for the other half at that breakpoint:

```json
{
    "responsive": {
        "textColor":        { "mobile": "accent" },
        "style.color.text": { "tablet": "#333333", "mobile": false }
    }
}
```

Unlike `null` (inherit), `false` stops the cascade, so on phones the Mobile preset isn't shown alongside the Tablet custom color. The renderers emit nothing for the marker and never let it displace the twin's value, so the canvas and the front end agree on which value wins at each breakpoint. Clearing one half removes the other half's marker, and the cascade resumes for both.

---

## 2. For developers

### 2.1 Configuring breakpoints

Breakpoints resolve in priority order — highest layer wins on key collision:

1. **Active theme's `theme.json`** — `settings.custom.artisanpack.breakpoints`
2. **Application config** — `artisanpack.visual-editor.breakpoints`
3. **Package defaults** — `BreakpointRegistry::DEFAULTS` (the `tablet` / `mobile` devices plus the legacy Tailwind v4 mins)

Each entry is either a **desktop-first device** breakpoint (declares `maxWidthPx`, emitted as `@media (max-width:Npx)`) or a **legacy mobile-first** breakpoint (declares `minWidthPx`, emitted as `@media (min-width:Npx)`). An entry can't declare both. Only device breakpoints appear in the viewport switcher.

Each entry accepts two forms:

- **Object** — `{ maxWidthPx, previewWidthPx, label }` for a device (or `{ minWidthPx, previewWidthPx, label }` for a legacy entry). `previewWidthPx` falls back to the width; `label` falls back to the key. A device's `previewWidthPx` can't exceed its `maxWidthPx`. Partial objects merge into the default at the same key, so you can override just one field without restating the others.
- **Scalar** — a single pixel value or `Npx` string. On an existing device key it sets the max-width (`'mobile' => '600px'`), clamping the inherited preview width into the new range; on a new key it creates a legacy min-width entry. Pre-#617 configs work unchanged.

To remove a key, set it to `null` or `''`.

#### Config example

```php
// config/artisanpack/visual-editor.php
return [
    'breakpoints' => [
        // Object form — override the switcher label and preview width:
        'mobile' => [
            'maxWidthPx'     => 767,
            'previewWidthPx' => 390,  // canvas iframe width (iPhone-sized preview)
            'label'          => 'Phone',
        ],

        // Partial override — keep the default `Tablet` label + 768px preview,
        // just relabel it:
        'tablet' => [ 'label' => 'iPad' ],

        // Add a third device breakpoint (small phones):
        'small-mobile' => [ 'maxWidthPx' => 479, 'previewWidthPx' => 360, 'label' => 'Small phone' ],
    ],
];
```

#### theme.json example

```json
{
    "settings": {
        "custom": {
            "artisanpack": {
                "breakpoints": {
                    "tablet": { "maxWidthPx": 1100, "previewWidthPx": 900 },
                    "mobile": "640px"
                }
            }
        }
    }
}
```

`maxWidthPx`, `minWidthPx`, and `previewWidthPx` accept integer pixels (`640`) or CSS-length strings (`'640px'`). Other lengths (`rem`, `vw`, …) are rejected at load time with a descriptive error. Two entries in the same family can't share a width. `label` must be a non-empty string. Keys may contain only letters, numbers, hyphens, and underscores.

The implicit `base` slot (the desktop design, applies everywhere) is reserved — using it as a key throws.

#### Ship defaults

Desktop-first devices (offered in the switcher):

| Key      | Label    | `maxWidthPx` | `previewWidthPx` |
| -------- | -------- | ------------ | ---------------- |
| `tablet` | `Tablet` | 1023         | 768              |
| `mobile` | `Mobile` | 767          | 375              |

Legacy mobile-first entries (render pre-1.12.1 content; not offered in the switcher):

| Key   | Label     | `minWidthPx` | `previewWidthPx` |
| ----- | --------- | ------------ | ---------------- |
| `sm`  | `Mobile`  | 640          | 375              |
| `md`  | `Tablet`  | 768          | 768              |
| `lg`  | `Desktop` | 1024         | 1440             |
| `xl`  | `xl+`     | 1280         | 1280             |
| `2xl` | `2xl+`    | 1536         | 1536             |

#### Emission order

Every emitter builds its media query through the registry (`BreakpointRegistry::mediaQuery()` in PHP, `registry.mediaQuery()` in TypeScript) and writes rules in registry order: legacy min-width entries ascending, then device max-width entries descending. The narrower device override comes last, so it wins the cascade on phones. This covers responsive spacing and other style overrides, column widths and counts, position, box shadows, gradient borders, animations, flex arbitrary values, and the React / Vue renderers.

#### Static CSS and custom widths

Some output is class-based rather than generated per request: the flex utility stylesheet (`tablet:ap-flex-col`, …), the Grid column-count classes, the Post Template span classes, and the masonry fallback. These ship `tablet` / `mobile` rules only for the default 1023px / 767px widths. If you change `tablet` or `mobile` widths (or add device keys), ship matching rules for those classes yourself. Since 1.15.0 the React / Vue renderers' generated rules (overrides, column widths, flex arbitrary values) follow the breakpoints you pass to `setBreakpoints()` (see [§2.5](#25-server-side-rendering)); before, they used the defaults.

#### Columns and Media & Text stacking

Since 1.12.1, Columns and Media & Text stack at the `mobile` breakpoint (767px by default) instead of the block library's fixed 781px / 600px thresholds, so the editor's Mobile preview and the front end agree. The Blade renderer builds these rules from the registry's `mobile` max-width; the bundled Columns and Media & Text block stylesheets use the default 767px. Columns marked *not stacked on mobile* and Media & Text blocks without *Stack on mobile* are left alone.

### 2.2 Opting a block into responsive support

Add `supports.artisanpackResponsive` to the block's `block.json`. List the attribute paths that should expose per-breakpoint UI:

```jsonc
{
    "name": "artisanpack/columns",
    "supports": {
        "artisanpackResponsive": {
            "attributes": [
                "spacing",
                "align",
                "columns.count"
            ]
        }
    }
}
```

Out of the box the following forked layout blocks opt in: `group`, `columns`, `column`, `buttons`, `spacer`, `cover`, `media-text`. Blocks that don't opt in still render correctly — their Inspector simply shows the single-value control as today.

Since 1.15.0 (#877) more paths are per-breakpoint:

| Block | New per-breakpoint paths |
| --- | --- |
| `heading`, `paragraph` (newly opted in) | `fontSize`, `textColor`, `style.color.text`, `backgroundColor`, `style.color.background` |
| `group` | `style.dimensions.minHeight`, `fontSize`, `textColor`, `style.color.text`, `backgroundColor`, `style.color.background` |
| `cover` | `minHeight`, `minHeightUnit`, `fontSize`, `textColor`, `style.color.text` |
| `spacer` | `width` (alongside `height`) |

Cover routes `minHeightUnit` per breakpoint too, so a unit-only override (`vh` at Mobile) renders.

### 2.3 Reading a responsive attribute in a block's edit component

```tsx
import { useResponsiveValue, registryFromSnapshot } from '@artisanpack-ui/visual-editor/responsive'

// `bootstrap.breakpoints` comes from the editor's PHP-stamped settings.
const registry = registryFromSnapshot( bootstrap.breakpoints )

export function Edit( { attributes }: BlockEditProps ) {
    const padding = useResponsiveValue<string>( attributes.padding, registry )

    return <div style={ { padding: padding ?? '0' } }>…</div>
}
```

`useResponsiveValue` re-renders the component every time the editor switches breakpoints, so the preview stays in sync without manual subscriptions.

### 2.4 Writing per-breakpoint values from an InspectorControl

Wrap the underlying primitive in `ResponsiveControl`. The wrapper handles promotion (scalar → discriminated form) and reset-to-base for you:

```tsx
import { ResponsiveControl } from '@artisanpack-ui/visual-editor/responsive'

<ResponsiveControl
    registry={ registry }
    value={ attributes.padding }
    onChange={ ( next ) => setAttributes( { padding: next } ) }
    label="Padding"
    render={ ( { value, setValue } ) => (
        <RangeControl
            value={ value ?? 0 }
            onChange={ ( v ) => setValue( v ) }
            min={ 0 }
            max={ 80 }
        />
    ) }
/>
```

### 2.5 Server-side rendering

The Blade renderer's `ResponsiveClassResolver` emits the correct class string or `@media` rule for any responsive attribute. Pass a token map when the values can be expressed as Tailwind utilities:

```php
use ArtisanPackUI\VisualEditorRendererBlade\Responsive\ResponsiveClassResolver;

$resolver = app( ResponsiveClassResolver::class );

$result = $resolver->emit(
    $attribute,            // [ 'base' => 3, 'tablet' => 2, 'mobile' => 1 ]
    'grid-template-columns',
    [
        '1' => 'grid-cols-1',
        '2' => 'grid-cols-2',
        '3' => 'grid-cols-3',
    ],
);

// $result['class'] → 'grid-cols-3 tablet:grid-cols-2 mobile:grid-cols-1'
// $result['css']   → '' (every value tokenized)
```

Token mode prefixes each utility with the breakpoint key. Tailwind has no built-in `tablet:` / `mobile:` variants, so if you pass a token map for attributes that carry device overrides, declare matching max-width custom variants in your Tailwind setup — or omit the token map and let the resolver emit `@media` CSS.

When the value can't be tokenized, the resolver falls back to a generated wrapper class plus the scoped CSS rules:

```php
$result = $resolver->emit(
    [ 'base' => '18px', 'mobile' => '13px' ],
    'font-size',
    [], // no token map
);

// $result['class'] → 've-r-abcd123456'
// $result['css']   → '.ve-r-abcd123456{font-size:18px}@media (max-width:767px){.ve-r-abcd123456{font-size:13px}}'
```

Block partials merge `$result['class']` into the wrapper class list and push `$result['css']` into the request-scoped `ResponsiveCssAccumulator`. The `<x-ve-blocks>` and `<x-ve-template>` components drain the accumulator at the top of the render output and emit one consolidated `<style data-ve-responsive>` block — there is no per-block `<style>` tag interleaved with the wrapper's children. Duplicate payloads (the same overrides on N siblings) collapse to one rule set, keyed by scope class.

Since 1.15.0 (#886) the React and Vue renderers emit per-breakpoint overrides too, byte-for-byte with Blade: each block with overrides gets a `ve-r-{hash}` class, and `BlockTree` prints the rules in one `<style data-ve-responsive>`. Their media queries come from the renderer's breakpoint list, which defaults to the package's Tablet (1023px) and Mobile (767px). A host that changes breakpoints (config `breakpoints` or `theme.json`) should pass the server's registry to `setBreakpoints()` at bootstrap, so column widths and overrides use the same widths as Blade:

```ts
import { setBreakpoints } from '@artisanpack-ui/visual-editor-renderer-react' // or -vue

// From `app( BreakpointRegistry::class )->toArray()`, passed down as a page prop:
setBreakpoints( props.breakpoints )

// Or a config-style map, layered over the defaults:
setBreakpoints( { mobile: { maxWidthPx: 599, previewWidthPx: 375 } } )
```

The array form replaces the legacy (min-width) list with its `minWidthPx` entries and the device list with its `maxWidthPx` entries; an array with no device entries keeps the default devices, so existing `setBreakpoints( [ { key, minWidthPx } ] )` calls behave as before. In the map form a number keeps the entry's family, an object naming one width switches family, and `null` removes the entry. Entries the server would reject (the `base` key, keys outside `[a-z0-9][a-z0-9_-]*`, non-positive or duplicate widths, both or neither width) are dropped, and a preview width wider than its max-width is clamped. `getDeviceBreakpoints()` returns the device list in use.

### 2.6 Lazy migration

Scalar values are first-class — they load without error and only inflate to the discriminated `{ base, tablet, mobile }` form the first time an editor sets a per-breakpoint override. There is no batch migration; existing content keeps working.

When every override is cleared back to inheriting the base, the storage collapses back to the scalar form so saved JSON stays compact.

### 2.7 Legacy mobile-first content

Content saved before 1.12.1 stores overrides under the mobile-first keys (`sm`, `md`, `lg`, `xl`, `2xl`). Nothing needs migrating:

- Those keys stay registered as legacy `min-width` entries, so saved content renders exactly as before in all three renderers.
- When you edit such a block at Tablet or Mobile, the editor preview folds in the legacy values whose min-width matches that preview width, so the canvas matches the front end. New overrides are written to `tablet` / `mobile`, which are emitted after the legacy rules and so win at their width.
- Legacy keys can't be selected in the switcher any more, but their values are preserved on save.

Hosts that customized the legacy keys in config or `theme.json` keep those customizations; to author new overrides, configure `tablet` / `mobile` (or your own `maxWidthPx` keys) instead.

### 2.8 Auditing orphaned overrides

When a theme or config removes a breakpoint that was previously in use, the values stored under that key are preserved on save but skipped at render time. Use the audit command to surface them:

```bash
php artisan visual-editor:audit-breakpoints
```

Sample output:

```
+-------------+-----------+------------------------------------------+
| Resource    | Record ID | Orphaned overrides                       |
+-------------+-----------+------------------------------------------+
| pages       | 42        | artisanpack/columns@spacing → [legacy]   |
+-------------+-----------+------------------------------------------+
Audited 17 record(s). 1 record(s) carry orphaned overrides.
```

Flags:

- `--resource=<slug>` — limit the audit to a single resource from `artisanpack.visual-editor.resources`.
- `--json` — emit a machine-readable report for CI.

---

## 3. What's out of scope (v1.0)

- **Container queries** — deferred to v1.x.
- **Per-breakpoint visibility** — deferred to v1.x (contextual visibility rules).
- **Per-breakpoint state styles** — future composition with [State Design Tools](State-Design-Tools.md).
- **Independent (non-cascading) modes** — desktop-first cascade only (mobile-first remains for legacy content).
- **Bulk migration of existing scalar attributes** — lazy promotion only.
