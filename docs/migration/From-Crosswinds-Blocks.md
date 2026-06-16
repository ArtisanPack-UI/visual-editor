# Migrating from `crosswinds-blocks` (#595)

The legacy `crosswinds-blocks` plugin added a narrow set of flexbox
helpers to `core/group`, `core/column`, and `core/post-template` —
three toggles plus a five-option dropdown. The ArtisanPack UI Flex
Layout panel covers the full flexbox surface and replaces the
crosswinds helpers entirely.

**There is no automatic migration**: crosswinds-blocks ran in a
separate project and we don't expect its content to land in
visual-editor pages. This page is the hand-migration reference if you
do bring content over.

## Mapping

| crosswinds attribute | New attribute | Notes |
| --- | --- | --- |
| `cbUseFlex: true` | `artisanpackFlex.container.enabled: { base: true }` | Toggle the Enable Flex switch. |
| `cbFillHeight: true` | `artisanpackFlex.item.grow: { base: 1 }` on the child you want to fill | Crosswinds applied this to the child; ours is a per-item property. |
| `cbInnerLayout: 'normal'` | (no flex; default flow) | Disable the panel. |
| `cbInnerLayout: 'equal'` | `direction: column` + `justifyContent: space-between` | Vertical column with children pushed apart. |
| `cbInnerLayout: 'center'` | `alignItems: center` (+ `justifyContent: center` if you want both axes) | Centers children. |
| `cbInnerLayout: 'bottom'` | `direction: column` + `justifyContent: flex-end` | All children pushed to the bottom. |
| `cbInnerLayout: 'last-bottom'` | On the last child: `alignSelf: flex-end` (column direction) or `marginTop: auto` (row direction) | Crosswinds pinned the last item; align-self gives equivalent control. |

## Why we didn't port the enum as-is

The crosswinds enum solved a real problem (authors couldn't reach
"fill remaining height" / "center children" without dropping into the
Advanced panel), but it masked a much richer property surface.
Replacing it with per-property controls means every author can express
any flexbox layout without writing CSS — the original goal — and the
panel grows naturally as flexbox itself does.
