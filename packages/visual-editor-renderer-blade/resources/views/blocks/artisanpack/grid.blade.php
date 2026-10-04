@php
	use ArtisanPackUI\VisualEditor\Responsive\BreakpointRegistry;
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;
	use ArtisanPackUI\VisualEditorRendererBlade\Support\PhotoGridSupport;

	$clampColumns = static function ( $value, int $fallback ): int {
		$int = is_numeric( $value ) ? (int) $value : $fallback;
		if ( $int < 1 ) {
			return 1;
		}
		if ( $int > 12 ) {
			return 12;
		}
		return $int;
	};

	$baseColumns = $clampColumns( $attributes['numColumns'] ?? null, 4 );

	// Per-breakpoint overrides. Mobile-first CSS cascade handles the rest:
	// emit `ap-grid-has-N-{bp}-columns` for every breakpoint that carries
	// its own value. grid.css orders the legacy min-width blocks
	// ascending and the desktop-first `tablet` / `mobile` max-width
	// blocks after them, so the narrower override wins (#820).
	$breakpointClasses = [ 'ap-grid-has-' . $baseColumns . '-base-columns' ];

	$responsiveColumns = $attributes['responsive']['numColumns'] ?? [];

	if ( is_array( $responsiveColumns ) ) {
		$registry = app( BreakpointRegistry::class );

		foreach ( $responsiveColumns as $bp => $value ) {
			if ( BreakpointRegistry::BASE_KEY === $bp ) {
				continue;
			}
			if ( ! is_numeric( $value ) ) {
				continue;
			}
			if ( ! $registry->has( (string) $bp ) ) {
				continue;
			}

			$breakpointClasses[] = 'ap-grid-has-' . $clampColumns( $value, $baseColumns ) . '-' . $bp . '-columns';
		}
	}

	$layoutMode = isset( $attributes['layoutMode'] ) && 'masonry' === $attributes['layoutMode']
		? 'masonry'
		: 'fixed';
	$isMasonry = 'masonry' === $layoutMode;

	$layoutClass = $isMasonry ? 'ap-grid-layout-masonry' : 'ap-grid-layout-fixed';

	$baseClasses = array_merge(
		[ 'ap-grid' ],
		$breakpointClasses,
		[ $layoutClass ],
		PhotoGridSupport::wrapperForBlock( $attributes )
	);

	$attrs = BlockSupports::wrapperAttrs( $attributes, $baseClasses );
	if ( $isMasonry ) {
		$attrs .= sprintf( ' data-ap-cols="%d"', $baseColumns );

		// Per-breakpoint column overrides ride alongside the base count
		// so the JS bootstrap can pick the active breakpoint at runtime
		// instead of locking the masonry layout to `data-ap-cols`.
		if ( is_array( $responsiveColumns ) ) {
			foreach ( $responsiveColumns as $bp => $value ) {
				if ( BreakpointRegistry::BASE_KEY === $bp ) {
					continue;
				}
				if ( ! is_numeric( $value ) ) {
					continue;
				}
				if ( ! $registry->has( (string) $bp ) ) {
					continue;
				}

				$attrs .= sprintf(
					' data-ap-cols-%s="%d"',
					e( (string) $bp ),
					$clampColumns( $value, $baseColumns )
				);
			}
		}
	}
@endphp
<div{!! $attrs !!}>
	{!! $innerBlocksHtml !!}
</div>
