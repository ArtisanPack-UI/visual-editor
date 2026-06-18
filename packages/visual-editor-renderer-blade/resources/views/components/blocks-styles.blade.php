@if( $emitBlockLibrary )
<link rel="stylesheet" href="{{ $styleHref }}" data-ve-block-library>
<link rel="stylesheet" href="{{ $themeHref }}" data-ve-block-library-theme>
@endif
@if( $emitInteractive )
<link rel="stylesheet" href="{{ $accordionStyleHref }}" data-ve-accordion>
<link rel="stylesheet" href="{{ $tabsStyleHref }}" data-ve-tabs>
<script src="{{ $interactivityScriptSrc }}" defer data-ve-interactivity></script>
@endif
{{-- Grid family CSS is layout-only (no interactivity script), so it
	loads independently of $emitInteractive. Hosts that opt out of
	accordion/tabs interactivity still want grid blocks to render. --}}
<link rel="stylesheet" href="{{ $gridStyleHref }}" data-ve-grid>
{{-- Marquee CSS ships the keyframes the inline `animation` declaration
	on the inner `<p>` references. Layout-only, same story as grid. --}}
<link rel="stylesheet" href="{{ $marqueeStyleHref }}" data-ve-marquee>
{{-- Social-share / author-social-icons layout + chip baseline. Reads
	the `--ap-social-*` custom properties stamped by the renderers so
	icon / hover / background color choices apply on the front end. --}}
<link rel="stylesheet" href="{{ $socialIconsStyleHref }}" data-ve-social-icons>
{{-- Breadcrumbs layout + separator baseline (#565). Matches the rules
	the editor loads from `blocks/breadcrumbs/breadcrumbs.css` so the
	server-stamped `<ol>` lays out inline with the chosen separator on
	the public frontend. --}}
<link rel="stylesheet" href="{{ $breadcrumbsStyleHref }}" data-ve-breadcrumbs>
{{-- Query Pagination layout baseline (#599). Matches the rules the
	editor loads from `blocks/query-pagination/query-pagination.css`
	so the server-rendered nav lays out horizontally with previous /
	numbers / next at start / center / end on the public frontend. --}}
<link rel="stylesheet" href="{{ $queryPaginationStyleHref }}" data-ve-query-pagination>
{{-- #595 — Flex layout utility classes (ap-flex, ap-flex-row, ap-gap-x-*, …)
	emitted by the shared serializer onto group/column/columns/grid-item
	wrappers. Static, layout-only, no interactivity needed. --}}
<link rel="stylesheet" href="{{ $flexLayoutStyleHref }}" data-ve-flex-layout>
{{-- #594 — Photo Grid container support. Targets descendant images of
	any container carrying `has-photo-grid` and reads the CSS custom
	properties stamped onto the wrapper via the inline rules emitted
	through `PhotoGridSupport::wrapperForBlock` + the responsive CSS
	accumulator. --}}
<link rel="stylesheet" href="{{ $photoGridStyleHref }}" data-ve-photo-grid>
{{-- #592 — Post Template grid container. Sets `grid-template-columns`
	on `.wp-block-post-template.is-layout-grid.columns-N` so the
	post-template's `is-layout-grid` wrapper renders as an actual
	N-column CSS grid (the layout baseline only supplies `display: grid`). --}}
<link rel="stylesheet" href="{{ $postTemplateStyleHref }}" data-ve-post-template>
{{-- #592 — Post Variant grid spans. Adds `ap-post-span-N-{bp}-{columns,row}`
	rules scoped to `.wp-block-post-template.is-layout-grid > .wp-block-post-template-item`
	so a variant's per-breakpoint `gridColumnSpan` / `gridRowSpan` only takes
	effect inside a grid-layout Query Loop. --}}
<link rel="stylesheet" href="{{ $postVariantStyleHref }}" data-ve-post-variant>
{{-- #593 — Masonry layout. Powers `post-template` (layout: masonry) and
	`grid` (layoutMode: masonry). Native CSS Grid masonry lights up
	automatically where supported via `@supports`; the JS fallback
	bootstrap below handles the rest. --}}
<link rel="stylesheet" href="{{ $masonryStyleHref }}" data-ve-masonry>
<script src="{{ $masonryScriptSrc }}" defer data-ve-masonry-fallback></script>
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
