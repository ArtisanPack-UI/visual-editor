@php
	use ArtisanPackUI\VisualEditor\Responsive\BreakpointRegistry;
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;
	use ArtisanPackUI\VisualEditorRendererBlade\Support\FlexSupport;

	$baseClasses = [ 'wp-block-column' ];

	if ( ! empty( $attributes['verticalAlignment'] ) ) {
		$baseClasses[] = 'is-vertically-aligned-' . $attributes['verticalAlignment'];
	}

	$baseClasses = array_merge( $baseClasses, FlexSupport::wrapperForBlock( $attributes ) );

	// `flex-basis` from the column's `width` attribute has to merge
	// with any block-supports inline style — compile() lets us splice
	// it in alongside the support declarations rather than emitting
	// two `style="…"` attributes (the second one would override the
	// first in some user agents).
	$compiled = BlockSupports::compile( $attributes );
	$classes  = array_values( array_unique( array_merge( $baseClasses, $compiled['classes'] ) ) );
	$styles   = '' !== $compiled['style'] ? rtrim( $compiled['style'], ';' ) : '';

	// Normalize a width attribute into a `flex-basis` value. Returns
	// `{ basis, percent }` — `basis` is the CSS expression to plug into
	// `flex-basis:`; `percent` is the numeric percent when the value is
	// a percentage (used downstream to subtract the parent's block-gap
	// from the basis), or `null` for absolute units (`100px`, `20em`).
	$normalizeBasis = static function ( $value ): array {
		if ( is_numeric( $value ) ) {
			$percent   = (float) $value;
			$formatted = rtrim( rtrim( sprintf( '%F', $percent ), '0' ), '.' );

			return [
				'basis'   => ( '' === $formatted ? '0' : $formatted ) . '%',
				'percent' => $percent,
			];
		}

		$string = (string) $value;

		if ( 1 === preg_match( '/^(\d+(?:\.\d+)?)\s*%$/', $string, $matches ) ) {
			return [
				'basis'   => $string,
				'percent' => (float) $matches[1],
			];
		}

		return [
			'basis'   => $string,
			'percent' => null,
		];
	};

	// For percentage widths, subtract the parent's block-gap from the
	// basis using the same `calc(X% - var(--wp--style--block-gap, ...) * factor)`
	// pattern WP uses for `wp-block-button.is-width-{N}` widths (see
	// `wp-block-library/style.css` lines 250-259). Without this, a
	// theme that sets `gap` on `.wp-block-columns` pushes
	// `50% + gap + 50%` past `100%` and the second column wraps to
	// a new line — visually identical to the stacking the WP mobile
	// rule produces.
	$basisExpr = static function ( array $normalized ) {
		if ( null === $normalized['percent'] ) {
			return $normalized['basis'];
		}

		$factor          = ( 100.0 - $normalized['percent'] ) / 100.0;
		$formattedFactor = rtrim( rtrim( sprintf( '%F', $factor ), '0' ), '.' );

		if ( '' === $formattedFactor || '0' === $formattedFactor ) {
			return $normalized['basis'];
		}

		return sprintf(
			'calc(%s - var(--wp--style--block-gap, 0.5em) * %s)',
			$normalized['basis'],
			$formattedFactor
		);
	};

	if ( ! empty( $attributes['width'] ) ) {
		$styles = ( '' !== $styles ? $styles . '; ' : '' ) . 'flex-basis: ' . $normalizeBasis( $attributes['width'] )['basis'];
	}

	$styleAttr = '' !== $styles ? sprintf( ' style="%s"', e( $styles . ';' ) ) : '';
	$idAttr    = null !== $compiled['id'] ? sprintf( ' id="%s"', e( $compiled['id'] ) ) : '';

	// #487 — column-specific responsive width.
	//
	// The column attribute `width` translates to `flex-basis` (not
	// `width`) so it can fight WP core's
	// `.wp-block-columns:not(.is-not-stacked-on-mobile) > .wp-block-column { flex-basis: 100% !important }`
	// stacking rule. The generic responsive emitter in BlockSupports
	// stays out of this path; emitting `width: X !important` wouldn't
	// help — the WP rule writes `flex-basis` directly.
	//
	// Two width sources merge here:
	//
	//  - `attributes.width` (the legacy scalar that the editor's HOC
	//    leaves alone at the `base` breakpoint — mobile-first cascade
	//    treats base as the unprefixed default, so it skips routing
	//    through `responsive.width`). Without promoting it into a
	//    class rule, the inline `style="flex-basis: 50%"` loses on
	//    specificity to WP's `!important` stacking rule on every
	//    viewport below 782px.
	//
	//  - `attributes.responsive.width.{sm,md,lg,…}` — explicit
	//    per-breakpoint overrides written through the HOC.
	//
	// We treat the legacy width as the implicit `base` entry and
	// merge it with the responsive overrides. The combined map
	// emits one rule per breakpoint with `flex-basis` AND
	// `flex-grow: 0` — both `!important`. `flex-grow: 0` matches WP's
	// own behavior for columns with explicit widths (otherwise
	// `flex-grow: 1` lets the column expand past the intended size on
	// rows with leftover space).
	$responsiveWidths = $attributes['responsive']['width'] ?? [];

	if ( ! is_array( $responsiveWidths ) ) {
		$responsiveWidths = [];
	}

	// Promote the legacy `width` attribute into the `base` slot when
	// the editor hasn't already written a base override through the
	// HOC. The HOC writes responsive overrides ONLY at non-base
	// breakpoints, so this branch picks up the "All sizes" value.
	if ( ! empty( $attributes['width'] ) && ! isset( $responsiveWidths['base'] ) ) {
		$responsiveWidths = [ 'base' => $attributes['width'] ] + $responsiveWidths;
	}

	if ( [] !== $responsiveWidths ) {
		// Use the request-scoped registry from the container so
		// host-configured breakpoints are respected — see the matching
		// comment in columns.blade.php for the full rationale.
		$widthRegistry = app( BreakpointRegistry::class );
		$widthScope    = 've-w-' . substr( hash( 'xxh3', (string) json_encode( $responsiveWidths ) ), 0, 10 );
		$widthRules    = [];
		// Triple-class selector matches the (0,0,3,0) specificity of
		// the WP stacking rule. Combined with `!important` on both
		// sides and source-order tiebreak (our consolidated style
		// block emits in `<body>` after every external stylesheet),
		// our rule wins the cascade.
		$widthSelector = sprintf( '.%1$s.%1$s.%1$s', $widthScope );

		foreach ( $responsiveWidths as $bp => $value ) {
			if ( null === $value || '' === $value ) {
				continue;
			}

			$declaration = sprintf(
				'flex-basis:%s!important;flex-grow:0!important',
				$basisExpr( $normalizeBasis( $value ) )
			);

			if ( BreakpointRegistry::BASE_KEY === $bp ) {
				$widthRules[] = sprintf( '%s{%s}', $widthSelector, $declaration );
				continue;
			}

			$minWidth = $widthRegistry->get( (string) $bp );
			if ( null === $minWidth ) {
				continue;
			}

			$widthRules[] = sprintf(
				'@media (min-width:%dpx){%s{%s}}',
				$minWidth,
				$widthSelector,
				$declaration
			);
		}

		if ( [] !== $widthRules ) {
			// Only attach the scope class to the wrapper once we
			// know the rules survived (every override might have
			// been filtered out as orphan breakpoints). An orphan
			// class on the wrapper would leak `ve-w-…` into the
			// markup without a corresponding rule in the
			// consolidated style block.
			$classes[] = $widthScope;

			// #509 — push into the per-request accumulator (see
			// columns.blade.php / BlockSupports::pushResponsive for
			// rationale).
			BlockSupports::pushResponsive( $widthScope, implode( '', $widthRules ) );
		}
	}
@endphp
<div class="{{ implode( ' ', $classes ) }}"{!! $styleAttr !!}{!! $idAttr !!}>
	{!! $innerBlocksHtml !!}
</div>
