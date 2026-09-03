@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;

	$faqSchema = (bool) ( $attributes['faqSchema'] ?? false );

	$faqJsonLd = '';

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

		if ( ! empty( $faqPairs ) ) {
			$encoded = json_encode(
				[
					'@context'   => 'https://schema.org',
					'@type'      => 'FAQPage',
					'mainEntity' => $faqPairs,
				],
				JSON_UNESCAPED_UNICODE | JSON_HEX_TAG
			);
			// json_encode returns false on invalid UTF-8; the whitespace
			// fallback above preserves those bytes, so guard here to
			// avoid emitting an empty `<script type="application/ld+json">`
			// tag when encoding fails.
			$faqJsonLd = false === $encoded ? '' : $encoded;
		}
	}
@endphp
<div{!! BlockSupports::wrapperAttrs( $attributes, [ 'ap-accordions' ] ) !!}>
	{!! $innerBlocksHtml !!}
	@if ( '' !== $faqJsonLd )
		<script type="application/ld+json">{!! $faqJsonLd !!}</script>
	@endif
</div>
