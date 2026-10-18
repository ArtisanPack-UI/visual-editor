{{--
	artisanpack/query-total — the query's result count (#962).

	Reads the `_resolved*` paginator snapshot QueryInliner stamps on the
	block: `total-results` prints "{prefix}<strong>25</strong> results{suffix}",
	`range-display` prints "{prefix}<strong>1–9</strong> of <strong>25</strong>
	results{suffix}" for the current page. The noun is `singular` for a total
	of exactly one, otherwise `plural`; a zero total prints the plural count in
	either mode. Renders nothing outside a resolved query (no `_resolvedTotal`).
	Mirrored by `QueryTotalBlock` in the React and Vue renderers.
--}}
@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;

	$hasTotal = isset( $attributes['_resolvedTotal'] ) && is_numeric( $attributes['_resolvedTotal'] );
	$total    = $hasTotal ? max( 0, (int) $attributes['_resolvedTotal'] ) : 0;

	$readText = static function ( string $key, string $default ) use ( $attributes ): string {
		return isset( $attributes[ $key ] ) && is_string( $attributes[ $key ] ) ? $attributes[ $key ] : $default;
	};

	$prefix = $readText( 'prefix', '' );
	$suffix = $readText( 'suffix', '' );
	$noun   = 1 === $total ? $readText( 'singular', 'result' ) : $readText( 'plural', 'results' );

	$range = null;
	if ( 'range-display' === ( $attributes['displayType'] ?? 'total-results' ) && $total > 0 ) {
		$current = isset( $attributes['_resolvedCurrentPage'] ) && is_numeric( $attributes['_resolvedCurrentPage'] ) ? max( 1, (int) $attributes['_resolvedCurrentPage'] ) : 1;
		$perPage = isset( $attributes['_resolvedPerPage'] ) && is_numeric( $attributes['_resolvedPerPage'] ) ? max( 0, (int) $attributes['_resolvedPerPage'] ) : 0;
		$items   = isset( $attributes['_resolvedItems'] ) && is_numeric( $attributes['_resolvedItems'] ) ? max( 0, (int) $attributes['_resolvedItems'] ) : null;

		$first = 0 === $perPage ? 1 : min( $total, ( $current - 1 ) * $perPage + 1 );
		$last  = 0 === $perPage ? $total : min( $total, $current * $perPage );
		if ( null !== $items && $items > 0 ) {
			$last = min( $total, $first + $items - 1 );
		}

		$range = $first === $last ? (string) $first : $first . '–' . $last;
	}
@endphp
@if ( $hasTotal )
	<div{!! BlockSupports::wrapperAttrs( $attributes, [ 'wp-block-query-total' ] ) !!}>{{ $prefix }}@if ( null !== $range )<strong>{{ $range }}</strong> of <strong>{{ $total }}</strong>@else<strong>{{ $total }}</strong>@endif{{ '' !== $noun ? ' ' . $noun : '' }}{{ $suffix }}</div>
@endif
