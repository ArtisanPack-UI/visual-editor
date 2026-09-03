@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;

	$rawSteps = $attributes['steps'] ?? [];

	if ( ! is_array( $rawSteps ) ) {
		$rawSteps = [];
	}

	// Round then clamp to match the React/Vue renderers so a fractional
	// or string attribute (e.g. 2.5 or "4") lands on the same tag across
	// every renderer.
	$rawLevel = $attributes['headingLevel'] ?? 3;
	$level    = is_numeric( $rawLevel ) ? (int) round( (float) $rawLevel ) : 3;

	if ( $level < 2 ) {
		$level = 2;
	} elseif ( $level > 6 ) {
		$level = 6;
	}

	$stepNameTag = 'h' . $level;

	$emitSchema = (bool) ( $attributes['emitSchema'] ?? true );

	$howtoName        = (string) ( $attributes['name'] ?? '' );
	$howtoDescription = (string) ( $attributes['description'] ?? '' );

	$normalize = static function ( string $value ): string {
		// Schema payload must be plain text so Google Search Console does
		// not flag the `wp-block-*` wrapper classes and inline tags as
		// noise (mirrors the FAQ handling). Insert a space at every
		// closing tag before stripping so adjacent block elements
		// (`<p>A.</p><p>B.</p>`) do not collapse into `A.B.`, then let
		// the whitespace collapse below normalize runs of separator
		// characters back to a single space.
		$withBreaks = preg_replace( '/<\/[a-z][^>]*>/i', '$0 ', $value );
		$plainRaw   = html_entity_decode(
			strip_tags( null === $withBreaks ? $value : $withBreaks ),
			ENT_QUOTES | ENT_HTML5,
			'UTF-8'
		);
		// preg_replace returns null on invalid UTF-8; fall back to the
		// pre-collapse string so trim() never receives null.
		$collapsed = preg_replace( '/\s+/u', ' ', $plainRaw );

		return trim( null === $collapsed ? $plainRaw : $collapsed );
	};

	$steps      = [];
	$schemaStep = [];
	$position   = 0;

	foreach ( $rawSteps as $step ) {
		if ( ! is_array( $step ) ) {
			continue;
		}

		$stepName = (string) ( $step['name'] ?? '' );
		$stepText = (string) ( $step['text'] ?? '' );
		$imageUrl = (string) ( $step['imageUrl'] ?? '' );
		$imageAlt = (string) ( $step['imageAlt'] ?? '' );

		if ( '' === trim( $stepName ) && '' === trim( $stepText ) ) {
			continue;
		}

		$steps[] = [
			'name'     => $stepName,
			'text'     => $stepText,
			'imageUrl' => $imageUrl,
			'imageAlt' => $imageAlt,
		];

		if ( ! $emitSchema ) {
			continue;
		}

		$plainName = $normalize( $stepName );
		$plainText = $normalize( $stepText );

		// schema.org's HowToStep requires `text`; fall back to `name`
		// so an author who only supplied a step title still produces
		// a valid payload. Skip the step entirely only when both
		// fields are blank.
		$schemaText = '' !== $plainText ? $plainText : $plainName;

		if ( '' === $schemaText ) {
			continue;
		}

		++$position;

		$entry = [
			'@type'    => 'HowToStep',
			'position' => $position,
			'text'     => $schemaText,
		];

		if ( '' !== $plainName ) {
			$entry['name'] = $plainName;
		}

		if ( '' !== trim( $imageUrl ) ) {
			$entry['image'] = $imageUrl;
		}

		$schemaStep[] = $entry;
	}

	$howtoJsonLd = '';

	if ( $emitSchema && ! empty( $schemaStep ) ) {
		$plainHowtoName        = $normalize( $howtoName );
		$plainHowtoDescription = $normalize( $howtoDescription );

		$payload = [
			'@context' => 'https://schema.org',
			'@type'    => 'HowTo',
			// schema.org requires `name` on HowTo; fall back to the
			// first step's name when the author left the block name
			// blank so we never emit an invalid payload.
			'name'     => '' !== $plainHowtoName
				? $plainHowtoName
				: ( $schemaStep[0]['name'] ?? '' ),
			'step'     => $schemaStep,
		];

		if ( '' !== $plainHowtoDescription ) {
			$payload['description'] = $plainHowtoDescription;
		}

		$encoded = json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG );
		// json_encode returns false on invalid UTF-8; guard here so a
		// broken payload does not emit an empty `<script>` tag.
		$howtoJsonLd = false === $encoded ? '' : $encoded;
	}
@endphp
<div{!! BlockSupports::wrapperAttrs( $attributes, [ 'ap-howto' ] ) !!}>
	@if ( '' !== $howtoName )
		<h2 class="ap-howto__name">{!! $howtoName !!}</h2>
	@endif
	@if ( '' !== $howtoDescription )
		<p class="ap-howto__description">{!! $howtoDescription !!}</p>
	@endif
	<ol class="ap-howto__steps">
		@foreach ( $steps as $step )
			<li class="ap-howto__step">
				<{{ $stepNameTag }} class="ap-howto__step-name">{!! $step['name'] !!}</{{ $stepNameTag }}>
				<div class="ap-howto__step-text">{!! $step['text'] !!}</div>
				@if ( '' !== trim( $step['imageUrl'] ) )
					<img class="ap-howto__step-image" src="{{ $step['imageUrl'] }}" alt="{{ $step['imageAlt'] }}"/>
				@endif
			</li>
		@endforeach
	</ol>
	@if ( '' !== $howtoJsonLd )
		<script type="application/ld+json">{!! $howtoJsonLd !!}</script>
	@endif
</div>
