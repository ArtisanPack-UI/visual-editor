@if( $emitBlockLibrary )
<link rel="stylesheet" href="{{ $styleHref }}" data-ve-block-library>
<link rel="stylesheet" href="{{ $themeHref }}" data-ve-block-library-theme>
@endif
@if( '' !== $themeTokensCss )
<style data-ve-theme-tokens>{!! $themeTokensCss !!}</style>
@endif
{{--
	Layout baseline rules — flex + grid.

	Upstream emits `display: flex; flex-wrap: wrap; align-items: center`
	per-instance via `useLayoutStyles` (editor) and
	`WP_Block_Supports_Layout` (PHP front-end). Our renderer-blade
	doesn't yet emit those dynamic rules; without them, blocks that
	declare `supports.layout.default.type = "flex"` in block.json
	(gallery, buttons, cover, group's row/stack variations, …) render
	as block flow on the public site even though the editor wrapper
	already carries `is-layout-flex`.

	Static fallback below mirrors `LAYOUT_DEFINITIONS.flex.baseStyles`
	+ `.grid.baseStyles` from @wordpress/block-editor. Per-block
	compound selectors (`wp-block-gallery-is-layout-flex`,
	`wp-block-cover-is-layout-flex`, …) inherit from the generic
	`.is-layout-flex` rule so we don't enumerate block names.
--}}
<style data-ve-layout-baseline>
.is-layout-flex { display: flex; flex-wrap: wrap; align-items: center; }
.is-layout-flex > :is(*, div) { margin: 0; }
.is-layout-grid { display: grid; }
.is-layout-grid > :is(*, div) { margin: 0; }
</style>
