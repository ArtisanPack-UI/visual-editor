@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;

	$rawItems = $attributes['items'] ?? [];

	if ( ! is_array( $rawItems ) ) {
		$rawItems = [];
	}

	$rawLevel = $attributes['headingLevel'] ?? 3;
	$level    = is_numeric( $rawLevel ) ? (int) $rawLevel : 3;

	if ( $level < 2 ) {
		$level = 2;
	} elseif ( $level > 6 ) {
		$level = 6;
	}

	$questionTag = 'h' . $level;

	$emitSchema = (bool) ( $attributes['emitSchema'] ?? true );

	$items    = [];
	$faqPairs = [];

	foreach ( $rawItems as $item ) {
		if ( ! is_array( $item ) ) {
			continue;
		}

		$question = (string) ( $item['question'] ?? '' );
		$answer   = (string) ( $item['answer'] ?? '' );

		if ( '' === trim( $question ) && '' === trim( $answer ) ) {
			continue;
		}

		$items[] = [
			'question' => $question,
			'answer'   => $answer,
		];

		if ( ! $emitSchema ) {
			continue;
		}

		// Schema payload must be plain text so Google Search Console does
		// not flag the `wp-block-*` wrapper classes and inline tags as
		// noise (mirrors the accordions FAQ toggle handling).
		$plainQuestion = trim( html_entity_decode( strip_tags( $question ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
		$plainAnswerRaw = html_entity_decode( strip_tags( $answer ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		// preg_replace returns null on invalid UTF-8; fall back to the
		// pre-collapse string so trim() never receives null.
		$collapsedAnswer = preg_replace( '/\s+/u', ' ', $plainAnswerRaw );
		$plainAnswer     = trim( null === $collapsedAnswer ? $plainAnswerRaw : $collapsedAnswer );

		if ( '' === $plainQuestion || '' === $plainAnswer ) {
			continue;
		}

		$faqPairs[] = [
			'@type'          => 'Question',
			'name'           => $plainQuestion,
			'acceptedAnswer' => [
				'@type' => 'Answer',
				'text'  => $plainAnswer,
			],
		];
	}

	$faqJsonLd = '';

	if ( $emitSchema && ! empty( $faqPairs ) ) {
		$encoded = json_encode(
			[
				'@context'   => 'https://schema.org',
				'@type'      => 'FAQPage',
				'mainEntity' => $faqPairs,
			],
			JSON_UNESCAPED_UNICODE | JSON_HEX_TAG
		);
		// json_encode returns false on invalid UTF-8; guard here so a
		// broken payload does not emit an empty `<script>` tag.
		$faqJsonLd = false === $encoded ? '' : $encoded;
	}
@endphp
<div{!! BlockSupports::wrapperAttrs( $attributes, [ 'ap-faq' ] ) !!}>
	@foreach ( $items as $item )
		<div class="ap-faq__item">
			<{{ $questionTag }} class="ap-faq__question">{!! $item['question'] !!}</{{ $questionTag }}>
			<div class="ap-faq__answer">{!! $item['answer'] !!}</div>
		</div>
	@endforeach
	@if ( '' !== $faqJsonLd )
		<script type="application/ld+json">{!! $faqJsonLd !!}</script>
	@endif
</div>
