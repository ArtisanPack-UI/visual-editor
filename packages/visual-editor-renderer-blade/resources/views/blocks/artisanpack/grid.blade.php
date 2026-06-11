@php
	use ArtisanPackUI\VisualEditor\Responsive\BreakpointRegistry;
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;

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
	// its own value. Ascending min-width ordering in grid.css means a
	// later breakpoint's class wins for the wider viewport.
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
			if ( null === $registry->get( (string) $bp ) ) {
				continue;
			}

			$breakpointClasses[] = 'ap-grid-has-' . $clampColumns( $value, $baseColumns ) . '-' . $bp . '-columns';
		}
	}

	$baseClasses = array_merge( [ 'ap-grid' ], $breakpointClasses );
@endphp
<div{!! BlockSupports::wrapperAttrs( $attributes, $baseClasses ) !!}>
	{!! $innerBlocksHtml !!}
</div>
