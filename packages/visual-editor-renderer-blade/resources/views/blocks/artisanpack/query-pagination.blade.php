@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;

	// QueryInliner stamps the page count; one page renders nothing, as in
	// WordPress (#955). An unstamped wrapper (no query around it) renders.
	$totalPages = isset( $attributes['_resolvedTotalPages'] ) && is_numeric( $attributes['_resolvedTotalPages'] ) ? (int) $attributes['_resolvedTotalPages'] : null;
@endphp
{{-- artisanpack/query-pagination — Phase I-Block-Fork query family (#521).
	Pagination wrapper around the next / numbers / previous children.

	Translation key intentionally avoids the bare word "Pagination":
	Laravel ships `vendor/laravel/framework/src/Illuminate/Translation/lang/en/pagination.php`
	which the JSON translator's case-insensitive fallback matches on
	`__( 'Pagination' )`, returning the file's array (`previous` /
	`next`) instead of a string and crashing `e()` downstream. --}}
@if ( null === $totalPages || $totalPages > 1 )
	<nav{!! BlockSupports::wrapperAttrs( $attributes, [ 'wp-block-query-pagination' ] ) !!} aria-label="{{ __( 'Pagination navigation' ) }}">
		{!! $innerBlocksHtml !!}
	</nav>
@endif
