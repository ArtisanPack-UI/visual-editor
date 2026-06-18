@php
	use ArtisanPackUI\VisualEditor\Responsive\BreakpointRegistry;
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;
	use ArtisanPackUI\VisualEditorRendererBlade\Support\FlexSupport;
	use ArtisanPackUI\VisualEditorRendererBlade\Support\PhotoGridSupport;

	$baseClasses = [ 'wp-block-columns' ];

	if ( ! empty( $attributes['isStackedOnMobile'] ) || ! isset( $attributes['isStackedOnMobile'] ) ) {
		$baseClasses[] = 'is-stacked-on-mobile';
	}

	if ( ! empty( $attributes['verticalAlignment'] ) ) {
		$baseClasses[] = 'are-vertically-aligned-' . $attributes['verticalAlignment'];
	}

	$baseClasses = array_merge( $baseClasses, FlexSupport::wrapperForBlock( $attributes ) );
	$baseClasses = array_merge( $baseClasses, PhotoGridSupport::wrapperForBlock( $attributes ) );

	// #487 — columns-specific responsive layout. `columnCount` overrides
	// translate to per-breakpoint `grid-template-columns` rules. The
	// generic responsive emitter in BlockSupports handles
	// padding/margin/border/etc.; the columns block declares its own
	// layout-specific path here.
	$columnCountOverrides = $attributes['responsive']['columnCount'] ?? null;

	if ( is_array( $columnCountOverrides ) && [] !== $columnCountOverrides ) {
		// Pull the request-scoped registry from the container so
		// host-configured breakpoints (theme.json + config) are
		// respected. `fromLayers()` without arguments would silently
		// return only the Tailwind defaults — any custom key (e.g.
		// `3xl`) would resolve to `null` here and the override would
		// be skipped at render even though it's persisted in the
		// block tree.
		$columnsRegistry = app( BreakpointRegistry::class );
		$columnsScope    = 've-cols-' . substr( hash( 'xxh3', (string) json_encode( $columnCountOverrides ) ), 0, 10 );
		$columnsRules    = [];

		// Tripled scope class matches BlockSupports::compileResponsive()
		// — boosts specificity to (0,0,3,0) so the grid rule beats
		// WP core's `.wp-block-columns:not(.is-not-stacked-on-mobile) > .wp-block-column { flex-basis: 100% !important }`
		// stacking rule that would otherwise force every column to
		// full-width at small viewports.
		$columnsSelector = sprintf( '.%1$s.%1$s.%1$s', $columnsScope );

		foreach ( $columnCountOverrides as $bp => $count ) {
			$intCount = (int) $count;

			if ( $intCount < 1 ) {
				continue;
			}

			$declarations = sprintf(
				'display:grid!important;grid-template-columns:repeat(%d,minmax(0,1fr))!important',
				$intCount
			);

			if ( BreakpointRegistry::BASE_KEY === $bp ) {
				$columnsRules[] = sprintf( '%s{%s}', $columnsSelector, $declarations );
				continue;
			}

			$minWidth = $columnsRegistry->get( (string) $bp );
			if ( null === $minWidth ) {
				continue;
			}

			$columnsRules[] = sprintf(
				'@media (min-width:%dpx){%s{%s}}',
				$minWidth,
				$columnsSelector,
				$declarations
			);
		}

		if ( [] !== $columnsRules ) {
			// Only attach the scope class once we know the rules
			// survived (every override might have been filtered out
			// as orphan breakpoints) — an orphan class on the
			// wrapper would leak `ve-cols-…` into the markup without
			// a corresponding rule in the consolidated style block.
			$baseClasses[] = $columnsScope;

			// #509 — push into the per-request accumulator instead of
			// inlining a `<style>` tag here. The `<x-ve-blocks>` /
			// `<x-ve-template>` components drain the accumulator into
			// one consolidated `<style data-ve-responsive>` block at
			// the top of the render output, keeping the flex
			// container free of interleaved style elements.
			BlockSupports::pushResponsive( $columnsScope, implode( '', $columnsRules ) );
		}
	}
@endphp
<div{!! BlockSupports::wrapperAttrs( $attributes, $baseClasses ) !!}>
	{!! $innerBlocksHtml !!}
</div>
