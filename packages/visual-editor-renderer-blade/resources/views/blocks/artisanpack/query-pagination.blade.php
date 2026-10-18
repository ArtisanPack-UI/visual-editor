@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;
	use ArtisanPackUI\VisualEditorRendererBlade\Support\LayoutSupport;

	// QueryInliner stamps the page count. On one page the previous / numbers
	// / next leaves render nothing, so a wrapper holding only those is
	// skipped, as WordPress skips an empty one (#955). Any other inner
	// block keeps it, and an unstamped wrapper (no query around it) renders.
	$totalPages = isset( $attributes['_resolvedTotalPages'] ) && is_numeric( $attributes['_resolvedTotalPages'] ) ? (int) $attributes['_resolvedTotalPages'] : null;
	$onlyLeaves = true;
	foreach ( isset( $innerBlocks ) && is_array( $innerBlocks ) ? $innerBlocks : [] as $child ) {
		$childName = is_array( $child ) && isset( $child['name'] ) && is_string( $child['name'] ) ? $child['name'] : '';
		if ( 1 !== preg_match( '#^(core|artisanpack)/query-pagination-(previous|numbers|next)$#', $childName ) ) {
			$onlyLeaves = false;
			break;
		}
	}
	$isRendered = null === $totalPages || $totalPages > 1 || ! $onlyLeaves;

	// Always a flex layout (block.json disallows switching), as in
	// `comments-pagination.blade.php`: the layout pair, a `wp-container-*`
	// scope for its justification, wrap and Block spacing, and WordPress's
	// layout modifier classes. With no saved justification or orientation,
	// `query-pagination.css` keeps its previous / numbers / next grid (#956).
	// A skipped wrapper pushes no container rule.
	$baseClasses = $isRendered
		? array_merge(
			[ 'wp-block-query-pagination' ],
			LayoutSupport::pair( 'query-pagination', 'is-layout-flex' ),
			LayoutSupport::containerClasses( $attributes, 'query-pagination', 'is-layout-flex' ),
			LayoutSupport::modifierClasses( $attributes )
		)
		: [];
@endphp
{{-- artisanpack/query-pagination — Phase I-Block-Fork query family (#521).
	Pagination wrapper around the next / numbers / previous children.

	Translation key intentionally avoids the bare word "Pagination":
	Laravel ships `vendor/laravel/framework/src/Illuminate/Translation/lang/en/pagination.php`
	which the JSON translator's case-insensitive fallback matches on
	`__( 'Pagination' )`, returning the file's array (`previous` /
	`next`) instead of a string and crashing `e()` downstream. --}}
@if ( $isRendered )
	<nav{!! BlockSupports::wrapperAttrs( $attributes, $baseClasses ) !!} aria-label="{{ __( 'Pagination navigation' ) }}">
		{!! $innerBlocksHtml !!}
	</nav>
@endif
