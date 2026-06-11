@if( $emitBlockLibrary )
<link rel="stylesheet" href="{{ $styleHref }}" data-ve-block-library>
<link rel="stylesheet" href="{{ $themeHref }}" data-ve-block-library-theme>
@endif
@if( $emitInteractive )
<link rel="stylesheet" href="{{ $accordionStyleHref }}" data-ve-accordion>
<link rel="stylesheet" href="{{ $tabsStyleHref }}" data-ve-tabs>
<script src="{{ $interactivityScriptSrc }}" defer data-ve-interactivity></script>
@endif
@if( '' !== $themeTokensCss )
<style data-ve-theme-tokens>{!! $themeTokensCss !!}</style>
@endif
{{--
	Layout baseline rules — flow + constrained + flex + grid.

	Upstream emits `display: flex; flex-wrap: wrap; align-items: center`
	per-instance via `useLayoutStyles` (editor) and
	`WP_Block_Supports_Layout` (PHP front-end). Our renderer-blade
	doesn't yet emit those dynamic rules; without them, blocks that
	declare `supports.layout.default.type = "flex"` in block.json
	(gallery, buttons, cover, group's row/stack variations, …) render
	as block flow on the public site even though the editor wrapper
	already carries `is-layout-flex`.

	Static fallback below mirrors `LAYOUT_DEFINITIONS.flow.baseStyles`,
	`.constrained.baseStyles`, `.flex.baseStyles`, and `.grid.baseStyles`
	from @wordpress/block-editor. Per-block compound selectors
	(`wp-block-gallery-is-layout-flex`, `wp-block-cover-is-layout-flex`,
	…) inherit from the generic `.is-layout-flex` rule so we don't
	enumerate block names.

	`:where()` on the flow/constrained selectors keeps specificity at
	(0,0,0) so any theme rule with higher specificity continues to win
	— matches WordPress's own output. The `var(--wp--style--block-gap,
	24px)` fallback mirrors core's default when `theme.json` does not
	set `styles.spacing.blockGap`.
--}}
<style data-ve-layout-baseline>
:where(.is-layout-flow) > :first-child { margin-block-start: 0; }
:where(.is-layout-flow) > :last-child { margin-block-end: 0; }
:where(.is-layout-flow) > * { margin-block-start: 0; margin-block-end: 0; }
:where(.is-layout-flow) > * + * { margin-block-start: var(--wp--style--block-gap, 24px); margin-block-end: 0; }
:where(.is-layout-constrained) > :first-child { margin-block-start: 0; }
:where(.is-layout-constrained) > :last-child { margin-block-end: 0; }
:where(.is-layout-constrained) > * { margin-block-start: 0; margin-block-end: 0; }
:where(.is-layout-constrained) > * + * { margin-block-start: var(--wp--style--block-gap, 24px); margin-block-end: 0; }
.is-layout-flex { display: flex; flex-wrap: wrap; align-items: center; }
.is-layout-flex > :is(*, div) { margin: 0; }
.is-layout-grid { display: grid; }
.is-layout-grid > :is(*, div) { margin: 0; }
</style>
