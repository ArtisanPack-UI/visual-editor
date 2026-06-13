@php
	use ArtisanPackUI\VisualEditorRendererBlade\Animations\AnimationMarkupResolver;
	use ArtisanPackUI\VisualEditorRendererBlade\Services\AnimationCssAccumulator;
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;

	$url    = (string) ( $attributes['url'] ?? '' );
	$alt    = (string) ( $attributes['alt'] ?? '' );
	$isDark = ! isset( $attributes['isDark'] ) || ! empty( $attributes['isDark'] );

	$dimRatio = isset( $attributes['dimRatio'] ) ? (int) $attributes['dimRatio'] : 50;
	$dimRatio = max( 0, min( 100, $dimRatio ) );

	$allowedUnits  = [ 'px', 'em', 'rem', 'vh', 'vw', '%' ];
	$minHeight     = null;
	$minHeightUnit = 'px';

	if ( isset( $attributes['minHeight'] ) && is_numeric( $attributes['minHeight'] ) ) {
		$minHeight = max( 0.0, (float) $attributes['minHeight'] );

		if ( isset( $attributes['minHeightUnit'] ) && in_array( $attributes['minHeightUnit'], $allowedUnits, true ) ) {
			$minHeightUnit = (string) $attributes['minHeightUnit'];
		}
	}

	$hasParallax = ! empty( $attributes['hasParallax'] );
	$isRepeated  = ! empty( $attributes['isRepeated'] );

	// Content-position attribute (e.g. "top left", "bottom right").
	// Whitelisted against the upstream `BACKGROUND_POSITIONS` matrix so
	// arbitrary attribute values can't inject classes.
	$allowedYPositions      = [ 'top', 'center', 'bottom' ];
	$allowedXPositions      = [ 'left', 'center', 'right' ];
	$contentPosition        = isset( $attributes['contentPosition'] ) && is_string( $attributes['contentPosition'] )
		? trim( $attributes['contentPosition'] )
		: '';
	$contentPositionClass   = '';
	$hasCustomContentPos    = false;

	if ( '' !== $contentPosition ) {
		[ $cpY, $cpX ] = array_pad( explode( ' ', $contentPosition, 2 ), 2, '' );

		if (
			in_array( $cpY, $allowedYPositions, true ) &&
			in_array( $cpX, $allowedXPositions, true )
		) {
			$contentPositionClass = sprintf( 'is-position-%s-%s', $cpY, $cpX );
			$hasCustomContentPos  = ! ( 'center' === $cpY && 'center' === $cpX );
		}
	}

	$baseClasses = [
		'wp-block-cover',
		$isDark ? 'is-dark' : 'is-light',
		'is-layout-flex',
		'wp-block-cover-is-layout-flex',
	];

	if ( $hasParallax ) {
		$baseClasses[] = 'has-parallax';
	}

	if ( $isRepeated ) {
		$baseClasses[] = 'is-repeated';
	}

	if ( '' !== $contentPositionClass ) {
		$baseClasses[] = $contentPositionClass;
	}

	if ( $hasCustomContentPos ) {
		$baseClasses[] = 'has-custom-content-position';
	}

	// Merge cover's own `min-height` declaration with whatever
	// block-supports style emerges from compile().
	$compiled = BlockSupports::compile( $attributes );
	$classes  = array_values( array_unique( array_merge( $baseClasses, $compiled['classes'] ) ) );
	$style    = '' !== $compiled['style'] ? rtrim( $compiled['style'], ';' ) : '';

	if ( null !== $minHeight ) {
		$style = ( '' !== $style ? $style . '; ' : '' ) . sprintf( 'min-height: %s%s', (string) $minHeight, $minHeightUnit );
	}

	$styleAttr = '' !== $style ? sprintf( ' style="%s"', e( $style . ';' ) ) : '';
	$idAttr    = null !== $compiled['id'] ? sprintf( ' id="%s"', e( $compiled['id'] ) ) : '';

	// #489 — resolve any block-animation attribute bag, attach the
	// wrapper classes + data attributes, and push the per-block CSS
	// into the accumulator so the BlocksComponent emits a single
	// `<style data-ve-animations>` block at the top of the response.
	$animationsAttr = is_array( $attributes['artisanpackAnimations'] ?? null )
		? (array) $attributes['artisanpackAnimations']
		: [];
	$animationsString = '';
	if ( [] !== $animationsAttr ) {
		// Scope-hash logic mirrors BlockSupports::resolveAnimations so a
		// block rendered by either path collides on the same scope key
		// and the accumulator dedupes correctly.
		$animationsJson   = json_encode( $attributes );
		$animationsSuffix = false === $animationsJson
			? substr( spl_object_hash( (object) $attributes ), 0, 8 )
			: substr( hash( 'sha1', $animationsJson ), 0, 8 );
		$animationsScope    = '.ap-block-' . $animationsSuffix;
		$animationsResolver = app( AnimationMarkupResolver::class );
		$animationsMarkup   = $animationsResolver->resolve( $animationsScope, $animationsAttr );

		if ( $animationsMarkup['hasAnimations'] ) {
			foreach ( $animationsMarkup['classes'] as $animationsClass ) {
				$classes[] = $animationsClass;
			}
			$classes[] = ltrim( $animationsScope, '.' );

			app( AnimationCssAccumulator::class )->push(
				$animationsScope,
				$animationsMarkup['css'],
				$animationsMarkup['noscriptCss'],
				$animationsMarkup['hasEntrance'],
			);

			$animationsString = $animationsResolver->dataString( $animationsMarkup['data'] );
			if ( '' !== $animationsString ) {
				$animationsString = ' ' . $animationsString;
			}
		}
	}
@endphp
<div class="{{ implode( ' ', $classes ) }}"{!! $styleAttr !!}{!! $idAttr !!}{!! $animationsString !!}>
	<span aria-hidden="true" class="wp-block-cover__background has-background-dim" style="opacity: {{ $dimRatio / 100 }};"></span>
	@if ( '' !== $url )
		<img class="wp-block-cover__image-background" alt="{{ $alt }}" src="{{ $url }}"/>
	@endif
	<div class="wp-block-cover__inner-container">
		{!! $innerBlocksHtml !!}
	</div>
</div>
