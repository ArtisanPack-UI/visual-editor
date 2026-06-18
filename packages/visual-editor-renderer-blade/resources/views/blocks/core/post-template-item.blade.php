@php
	use ArtisanPackUI\VisualEditor\Responsive\BreakpointRegistry;

	$postId = isset( $attributes['postId'] ) ? (int) $attributes['postId'] : 0;
	$className = isset( $attributes['className'] ) && is_string( $attributes['className'] )
		? trim( $attributes['className'] )
		: '';

	$spanClasses = [];
	$resolvedSpan = $attributes['_resolvedGridSpan'] ?? null;

	if ( is_array( $resolvedSpan ) ) {
		$breakpointRegistry = app( BreakpointRegistry::class );

		$emitSpan = static function ( $overrides, string $suffix ) use ( $breakpointRegistry ): array {
			$out = [];

			if ( ! is_array( $overrides ) ) {
				return $out;
			}

			foreach ( $overrides as $bp => $value ) {
				if ( ! is_string( $bp ) || '' === $bp ) {
					continue;
				}

				if ( ! is_numeric( $value ) ) {
					continue;
				}

				if ( BreakpointRegistry::BASE_KEY !== $bp && null === $breakpointRegistry->get( $bp ) ) {
					continue;
				}

				$amount = (int) $value;

				if ( $amount < 1 ) {
					$amount = 1;
				} elseif ( $amount > 12 ) {
					$amount = 12;
				}

				$out[] = 'ap-post-span-' . $amount . '-' . $bp . '-' . $suffix;
			}

			return $out;
		};

		$spanClasses = array_merge(
			$emitSpan( $resolvedSpan['columns'] ?? null, 'columns' ),
			$emitSpan( $resolvedSpan['rows'] ?? null, 'row' ),
		);
	}

	$classes = array_filter( array_merge( [ 'wp-block-post-template-item', $className ], $spanClasses ) );
	$classAttr = sprintf( 'class="%s"', e( implode( ' ', $classes ) ) );
	$idAttr = $postId > 0 ? sprintf( ' id="post-%d"', $postId ) : '';
@endphp
<li{!! $idAttr !!} {!! $classAttr !!}>
	{!! $innerBlocksHtml !!}
</li>
