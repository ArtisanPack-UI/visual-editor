{{--
	artisanpack/related-posts — related-by-taxonomy loop (#501).

	`QueryInliner` resolves N related posts for the host entry and clones
	the saved inner-block tree once per result with `_resolved*` stamps
	applied through `PostResolver`. This template renders the outer
	wrapper plus an inner row per iteration; when no results matched
	(`_resolvedItems` is zero) the wrapper renders nothing so the
	surrounding layout collapses cleanly.

	#964 — when the related items sit directly under the block (one
	synthetic `core/post-template-item` per result), `displayLayout`
	`{ type: 'grid', columns }` makes the wrapper the grid: it takes Post
	Template's `is-layout-grid` + `columns-N` classes, so post-template.css
	supplies the tracks, the Block spacing gap and the responsive collapse.
	A nested Post Template owns the layout itself, so the wrapper stays a
	plain block there.
--}}
@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;
	use ArtisanPackUI\VisualEditorRendererBlade\Support\LayoutSupport;

	$rawItems = $attributes['_resolvedItems'] ?? 0;
	$items    = is_numeric( $rawItems ) ? (int) $rawItems : 0;

	$hasPostTemplate = false;

	foreach ( $innerBlocks ?? [] as $child ) {
		$childName = is_array( $child ) && isset( $child['name'] ) && is_string( $child['name'] ) ? $child['name'] : '';

		if ( 'core/post-template' === $childName || 'artisanpack/post-template' === $childName ) {
			$hasPostTemplate = true;
			break;
		}
	}

	$displayLayout = isset( $attributes['displayLayout'] ) && is_array( $attributes['displayLayout'] )
		? $attributes['displayLayout']
		: [];

	$isGrid = ! $hasPostTemplate && 'grid' === ( $displayLayout['type'] ?? null );

	$classes = [ 'ap-related-posts' ];

	if ( $isGrid ) {
		// `displayLayout.columns`, else the legacy `numColumns`, else
		// Post Template's default of 3. Parsed and clamped the way
		// post-template.blade.php parses its `columns`, so the JS
		// renderers' `attrInt()` agrees on numeric strings.
		$rawColumns = $displayLayout['columns'] ?? ( $attributes['numColumns'] ?? null );

		if ( is_int( $rawColumns ) || is_float( $rawColumns ) ) {
			$columns = (int) $rawColumns;
		} elseif ( is_string( $rawColumns ) && 1 === preg_match( '/^\s*([+-]?\d+)/', $rawColumns, $columnsMatch ) ) {
			$columns = (int) $columnsMatch[1];
		} else {
			$columns = 3;
		}

		$columns = max( 1, min( 12, $columns ) );

		$classes   = array_merge( $classes, LayoutSupport::pair( 'artisanpack-related-posts', 'is-layout-grid' ) );
		$classes[] = 'columns-' . $columns;
	}
@endphp
@if ( $items > 0 )
	<div{!! BlockSupports::wrapperAttrs( $attributes, $classes ) !!}>{!! $innerBlocksHtml !!}</div>
@endif
