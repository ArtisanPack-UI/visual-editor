# Responsive Design Tools

**Status:** v1.0

The editor lets editors and developers author per-breakpoint style and structural overrides without hand-editing CSS. The same registry that drives the editor UI also drives the server-side renderer, so previews match production exactly.

This document covers both the editor-facing workflow and the developer integration surface. See also:

- [Theming](../post-editor/Theming.md) — for the breakpoint registry hierarchy (where overrides live).
- [Configuration](../Configuration.md#breakpoints) — for the host-app config override path.

---

## 1. For editors

### 1.1 The viewport switcher

The editor toolbar shows one button per registered breakpoint, plus an `All sizes` button on the left for the unprefixed base value:

```
[ All sizes ] [ Mobile ] [ Tablet ] [ Desktop ] [ xl ] [ 2xl ]
```

Selecting a button does two things atomically (#617):

1. **Resizes the canvas** to the breakpoint's `previewWidthPx` so you can preview the layout at that device size. `All sizes` clears the width constraint and the canvas fills the available editor area.
2. **Scopes the next style edit** to that breakpoint. Any Inspector control that supports responsive overrides records your change at the active breakpoint.

The `All sizes` button writes to the unprefixed base value — the value that applies everywhere unless a larger breakpoint overrides it.

The three shipped device presets — `Mobile` (`sm`, 375px canvas), `Tablet` (`md`, 768px canvas), `Desktop` (`lg`, 1440px canvas) — preview at real device viewport widths, not at the CSS `min-width` that activates the breakpoint's cascade. The `sm` cascade activates at Tailwind's default 640px, but you preview it on a phone-sized 375px canvas because that is the device the CSS is authored for.

The same switcher drives the post editor, the site editor (templates, template parts), and the patterns editor.

### 1.2 Setting a per-breakpoint override

1. Select the block you want to customize.
2. Click the viewport switcher button for the breakpoint you want to target (e.g. **Mobile**).
3. Adjust the control (padding, font size, column count, alignment, …) in the Inspector.

The value you just set applies at that breakpoint and up, until another override at a larger breakpoint replaces it.

### 1.3 Resetting an override

Each per-breakpoint control surfaces a **Reset to base** button when an override is currently set at the active breakpoint. Clicking it removes that single override and lets the value cascade through from the next-smaller defined slot.

If clearing the override leaves no other overrides, the stored attribute collapses back to the simple scalar form — no extra JSON on disk.

### 1.4 Mobile-first cascade

The cascade is always mobile-first:

- `base` applies everywhere unless overridden.
- A value set at `md` applies at `md` (≥768px) and up, unless `lg`, `xl`, or `2xl` overrides it.
- A value set at `lg` does **not** affect `sm` or `md` — those continue to inherit `base` (or whatever smaller breakpoint was set).

This mirrors the way Tailwind's `sm:` / `md:` modifiers work in CSS.

---

## 2. For developers

### 2.1 Configuring breakpoints

Breakpoints resolve in priority order — highest layer wins on key collision:

1. **Active theme's `theme.json`** — `settings.custom.artisanpack.breakpoints`
2. **Application config** — `artisanpack.visual-editor.breakpoints`
3. **Package defaults** — `BreakpointRegistry::DEFAULTS` (Tailwind v4 mins)

Each entry accepts two forms:

- **Scalar** — a single pixel value or `Npx` string. `previewWidthPx` defaults to the same value; `label` defaults to the key. Pre-#617 configs work unchanged.
- **Object** (#617) — `{ minWidthPx, previewWidthPx, label }`. `minWidthPx` is required; `previewWidthPx` falls back to `minWidthPx`; `label` falls back to the key. Partial objects merge into the default at the same key, so you can override just one field without restating the others.

#### Config example

```php
// config/artisanpack/visual-editor.php
return [
    'breakpoints' => [
        // Object form — override the switcher label and preview width:
        'sm'  => [
            'minWidthPx'     => 640,
            'previewWidthPx' => 390,  // canvas iframe width (iPhone-sized preview)
            'label'          => 'iPhone',
        ],

        // Partial override — keep the default `Desktop` label + 1440px preview,
        // just move the media-query threshold:
        'lg'  => [ 'minWidthPx' => 1100 ],

        // Scalar form — still supported. Resolves to
        // `{ minWidthPx: 1920, previewWidthPx: 1920, label: '3xl' }`.
        '3xl' => 1920,
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
                    "lg":  { "previewWidthPx": 1600 },
                    "3xl": 1920
                }
            }
        }
    }
}
```

`minWidthPx` and `previewWidthPx` accept integer pixels (`640`) or CSS-length strings (`'640px'`). Other lengths (`rem`, `vw`, …) are rejected at load time with a descriptive error. `label` must be a non-empty string.

The implicit `base` slot (no min-width, applies everywhere) is reserved — using it as a key throws.

#### Ship defaults

| Key   | Label     | `minWidthPx` | `previewWidthPx` |
| ----- | --------- | ------------ | ---------------- |
| `sm`  | `Mobile`  | 640          | 375              |
| `md`  | `Tablet`  | 768          | 768              |
| `lg`  | `Desktop` | 1024         | 1440             |
| `xl`  | `xl`      | 1280         | 1280             |
| `2xl` | `2xl`     | 1536         | 1536             |

The `minWidthPx` / `previewWidthPx` split lets the `sm` media query kick in at Tailwind's default 640px while the canvas previews on a 375px phone — the size the CSS is authored for.

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
    $attribute,            // [ 'base' => 3, 'sm' => 1, 'md' => 2 ]
    'grid-template-columns',
    [
        '1' => 'grid-cols-1',
        '2' => 'grid-cols-2',
        '3' => 'grid-cols-3',
    ],
);

// $result['class'] → 'grid-cols-3 sm:grid-cols-1 md:grid-cols-2'
// $result['css']   → '' (every value tokenized)
```

When the value can't be tokenized, the resolver falls back to a generated wrapper class plus the scoped CSS rules:

```php
$result = $resolver->emit(
    [ 'base' => '13px', 'md' => '18px' ],
    'font-size',
    [], // no token map
);

// $result['class'] → 've-r-abcd123456'
// $result['css']   → '.ve-r-abcd123456{font-size:13px}@media (min-width:768px){.ve-r-abcd123456{font-size:18px}}'
```

Block partials merge `$result['class']` into the wrapper class list and push `$result['css']` into the request-scoped `ResponsiveCssAccumulator`. The `<x-ve-blocks>` and `<x-ve-template>` components drain the accumulator at the top of the render output and emit one consolidated `<style data-ve-responsive>` block — there is no per-block `<style>` tag interleaved with the wrapper's children. Duplicate payloads (the same overrides on N siblings) collapse to one rule set, keyed by scope class.

### 2.6 Lazy migration

Scalar values are first-class — they load without error and only inflate to the discriminated `{ base, sm, … }` form the first time an editor sets a per-breakpoint override. There is no batch migration; existing content keeps working.

When every override is cleared back to inheriting the base, the storage collapses back to the scalar form so saved JSON stays compact.

### 2.7 Auditing orphaned overrides

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
- **Desktop-first or independent (non-cascading) modes** — mobile-first only.
- **Bulk migration of existing scalar attributes** — lazy promotion only.
