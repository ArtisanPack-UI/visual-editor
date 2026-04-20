@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\UrlSanitizer;

	$text   = (string) ( $attributes['text'] ?? '' );
	$url    = UrlSanitizer::safe( (string) ( $attributes['url'] ?? '' ) );
	$target = (string) ( $attributes['linkTarget'] ?? '' );
	$rel    = (string) ( $attributes['rel'] ?? '' );
	$title  = (string) ( $attributes['title'] ?? '' );

	if ( '_blank' === $target ) {
		$relTokens = array_filter( preg_split( '/\s+/', $rel ) ?: [] );

		foreach ( [ 'noopener', 'noreferrer' ] as $required ) {
			if ( ! in_array( $required, $relTokens, true ) ) {
				$relTokens[] = $required;
			}
		}

		$rel = implode( ' ', $relTokens );
	}

	$wrapperClasses = [ 'wp-block-button' ];

	if ( ! empty( $attributes['className'] ) ) {
		$wrapperClasses[] = $attributes['className'];
	}

	$linkClasses = [ 'wp-block-button__link', 'wp-element-button' ];

	$linkAttrs = '';

	if ( '' !== $target ) {
		$linkAttrs .= sprintf( ' target="%s"', e( $target ) );
	}

	if ( '' !== $rel ) {
		$linkAttrs .= sprintf( ' rel="%s"', e( $rel ) );
	}

	if ( '' !== $title ) {
		$linkAttrs .= sprintf( ' title="%s"', e( $title ) );
	}
@endphp
<div class="{{ implode( ' ', array_map( 'trim', $wrapperClasses ) ) }}">
	@if ( '' !== $url )
		<a class="{{ implode( ' ', $linkClasses ) }}" href="{{ $url }}"{!! $linkAttrs !!}>{!! $text !!}</a>
	@else
		<span class="{{ implode( ' ', $linkClasses ) }}">{!! $text !!}</span>
	@endif
</div>
