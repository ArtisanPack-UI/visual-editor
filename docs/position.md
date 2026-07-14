# CSS Position

The Position panel lets editors pin, layer, or precisely place any
block that opts into `supports.position`. It extends Gutenberg's
built-in `position` support — the native shape stores a plain
`"sticky"` string; this system layers a structured object on top of
the same attribute path so all five CSS `position` values, per-side
offsets, `z-index`, and per-breakpoint overrides are first-class.

Parent tracking issue: [#640](https://github.com/ArtisanPack-UI/visual-editor/issues/640).

## Attribute shape

Stored on the block as `attributes.style.position`:

```json
{
    "value": "sticky",
    "offsets": {
        "top":    { "value": 0,  "unit": "px" },
        "bottom": { "value": 16, "unit": "px" }
    },
    "zIndex": 10
}
```

- `value`: one of `static | relative | absolute | fixed | sticky`.
- `offsets.<side>`: `{ value: number, unit: 'px' | '%' | 'rem' | 'em' | 'vh' | 'vw' | 'auto' } | null`.
  Empty means unset (not `0`).
- `zIndex`: integer or `null`.

Per-breakpoint overrides ride the standard responsive bag,
`attributes.responsive['style.position']`, keyed by the breakpoint
prefix (`sm`, `md`, `lg`, `xl`, `2xl`). Missing fields inherit from
the next-smaller defined breakpoint, then from `base` — the same
mobile-first cascade the rest of the responsive system uses (see
[#487](https://github.com/ArtisanPack-UI/visual-editor/issues/487)).

Legacy content that stored `style.position` as a bare string (Gutenberg's
native sticky shape) is coerced to `{ "value": "sticky" }` on read
without touching the persisted attribute — no migration required.

## Opting a custom block in

Set `supports.position: true` in the block's `block.json`:

```json
{
    "name": "myplugin/callout",
    "supports": {
        "position": true
    }
}
```

Gutenberg's own object shape (`"position": { "sticky": true }`) also
counts as opted in — both flip the same gate. If a block has neither,
the Position panel doesn't render for it and no attributes are
stamped.

Child-only blocks (those with a `parent` or `ancestor` restriction that
locks them inside a specific container — `column` inside `columns`,
`accordion-title` inside `accordion`) should be deliberately left off;
positioning a child that a container relies on for layout will break
the container's contract.

## Position values

- **static** (default) — normal flow, no CSS emitted. Offsets and
  `z-index` are preserved on the attribute so a flip back to a
  positioned value doesn't lose values.
- **relative** — creates a positioning context for absolute descendants
  and honors offsets from its natural flow location.
- **absolute** — takes the block out of flow. Positioned against the
  nearest positioned ancestor (see the warning notice below).
- **fixed** — positioned against the viewport. Ignores containing
  block scroll.
- **sticky** — behaves as `relative` until the block's containing
  scroll container reaches the offset, then pins.

## Offset units

`px`, `%`, `rem`, `em`, `vh`, `vw`, `auto`. `auto` is unit-only — no
numeric value. Emitted as `top: auto` etc., matching CSS semantics.

## Per-breakpoint inheritance

Values are resolved mobile-first. Setting an offset only at `md`
means: `base` and `sm` inherit whatever was set at `base` (or
nothing); `md` and larger get the `md` override. The panel follows
the editor's top-bar viewport switcher — flip to a different
breakpoint there and the panel re-reads its effective values for
that breakpoint. The inspector surfaces an **Inherited** hint next
to any field that isn't explicitly overridden at the currently-viewed
breakpoint so authors know which value they'd have to change to
break inheritance.

The three values (position, offsets, `z-index`) inherit
independently — you can override just `z-index` at `md` and the
position + offsets keep flowing from `base`.

## Positioned-ancestor warning

When a block is set to `position: absolute` at the currently-viewed
breakpoint and none of its ancestor blocks is positioned, the
inspector renders a warning:

> This block is set to `position: absolute` but none of its ancestor
> blocks is positioned. It will position relative to the nearest
> positioned ancestor — often the page.

No ancestor is mutated automatically. The block still applies its
position — the warning is a heads-up that the placement will resolve
against the page (or whichever ancestor happens to be positioned),
not necessarily against the block's immediate parent.

To fix, set the parent block's position to any non-static value
(`relative` is the usual choice — it creates a positioning context
without changing the parent's own placement).

## Rendered CSS

Two channels emit on the frontend:

- **Wrapper inline style** — the base layer stamps as inline
  declarations (`style="position: sticky !important; top: 0px !important;"`)
  so the position survives hosts that strip `<style>` blocks from
  block markup.
- **`<style data-ve-position>` block** — the Blade renderer's per-request
  accumulator emits a single `<style>` element at the top of the
  response with per-breakpoint rules wrapped in `@media (min-width:...)`
  queries. Scope class is `.ve-pos-<id>` — same id lands on the
  wrapper.

Both use `!important` on every declaration. The editor canvas's own
`.block-editor-block-list__layout .block-editor-block-list__block`
rule sets `position: relative` at higher specificity than a single
scope class, and theme resets frequently ship `!important` position
declarations on wrapper classes. Matching that specificity level is
the only way to reliably honor the user's explicit pick.

## Troubleshooting

**Sticky doesn't stick.** Sticky needs three things: (1) a non-`auto`
offset (usually `top`), (2) a scroll context — the block's nearest
ancestor with a defined height and `overflow: auto | scroll | hidden`,
and (3) the sticky block cannot fill its scroll container. If the
container's height exactly matches the sticky block's height, there's
nowhere to scroll and no sticky travel. Check that the parent column
has enough content below the sticky block to trigger scrolling.

**Absolute block sits in the wrong place.** The warning notice above
covers the common cause. If the parent is positioned but the offsets
still look wrong, check for a Gutenberg layout wrapper (e.g. `.wp-block-group__inner-container`)
between the block and its declared parent — layout wrappers create
their own containing block and may shift the coordinate origin.

**Frontend doesn't pick up new rules after an upgrade.** Hosts that
previously ran `php artisan vendor:publish --tag=visual-editor-blade-views`
have published copies of the Blade templates that shadow the package
source. Older published copies don't include the `<style data-ve-position>`
output block — the accumulator flushes into the void. Republish with:

```bash
php artisan vendor:publish --tag=visual-editor-blade-views --force
```

**Editor canvas shows position but the frontend doesn't.** Check the
page source for a `<style data-ve-position>` element AND a
`style="position: …"` inline attribute on the wrapper `<div>`. If
neither is present, the block's parent partial isn't calling
`BlockSupports::wrapperAttrs()` — verify the block's Blade template
uses that helper (or an equivalent that pipes `compile()` through
the wrapper's `class` + `style` attributes).

## Related

- [Animations](animations.md) — same accumulator / scope-class pattern.
- [Box Shadows](box-shadows.md) — same accumulator pattern.
- [Border Gradients](border-gradients.md) — same accumulator pattern.
