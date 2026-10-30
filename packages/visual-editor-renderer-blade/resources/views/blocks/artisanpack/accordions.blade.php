@php
	use ArtisanPackUI\VisualEditorRendererBlade\Services\FaqSchemaAccumulator;
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;
	use ArtisanPackUI\VisualEditorRendererBlade\Support\LayoutSupport;

	// #933 — the panels' spacing is a flex `gap`. The spacing support only
	// prints a string Block spacing as `--wp--style--block-gap`, which the
	// stylesheet can't read without inheriting an ancestor's gap, so the
	// block's own value is printed as `gap` here. A per-axis value already
	// prints `row-gap`.
	$ownGap = LayoutSupport::blockGapValue( $attributes['style']['spacing']['blockGap'] ?? null );

	$faqSchema = (bool) ( $attributes['faqSchema'] ?? false );

	if ( $faqSchema && ! empty( $innerBlocks ) ) {
		$renderer = app( \ArtisanPackUI\VisualEditorRendererBlade\BlockRenderer::class );

		$faqPairs = [];

		foreach ( $innerBlocks as $panel ) {
			if ( ! is_array( $panel ) || 'artisanpack/accordion' !== ( $panel['name'] ?? '' ) ) {
				continue;
			}

			$question = '';
			$answer   = '';

			foreach ( $panel['innerBlocks'] ?? [] as $child ) {
				if ( ! is_array( $child ) ) {
					continue;
				}

				$childName = $child['name'] ?? '';
				$childHtml = ! empty( $child['innerBlocks'] ) && is_array( $child['innerBlocks'] )
					? $renderer->render( $child['innerBlocks'] )
					: '';

				if ( 'artisanpack/accordion-title' === $childName ) {
					$question = trim( html_entity_decode( strip_tags( $childHtml ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
					continue;
				}

				if ( 'artisanpack/accordion-body' === $childName ) {
					// Plain text answer: strip the renderer's wrapper tags
					// and class attributes so the schema payload doesn't
					// leak `wp-block-*` markup that Google Search Console
					// flags as noise. Collapse internal whitespace so
					// stripped block boundaries don't leave double spaces.
					$plain  = html_entity_decode( strip_tags( $childHtml ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
					// preg_replace returns null on invalid UTF-8; fall back
					// to the pre-collapse string so trim() never gets null
					// (a TypeError under PHP 8.x's stricter internal casts).
					$collapsed = preg_replace( '/\s+/u', ' ', $plain );
					$answer    = trim( null === $collapsed ? $plain : $collapsed );
					continue;
				}
			}

			if ( '' === $question || '' === $answer ) {
				continue;
			}

			$faqPairs[] = [
				'@type'          => 'Question',
				'name'           => $question,
				'acceptedAnswer' => [
					'@type' => 'Answer',
					'text'  => $answer,
				],
			];
		}

		// #1034 — queued, not printed, so every FAQ on the page lands in
		// one `FAQPage` script once the tree has rendered.
		if ( ! empty( $faqPairs ) ) {
			app( FaqSchemaAccumulator::class )->push( $faqPairs );
		}
	}
@endphp
<div{!! BlockSupports::wrapperAttrs( $attributes, [ 'wp-block-artisanpack-accordions', 'ap-accordions' ], null === $ownGap ? [] : [ 'gap: ' . $ownGap ] ) !!}>
	{!! $innerBlocksHtml !!}
</div>
