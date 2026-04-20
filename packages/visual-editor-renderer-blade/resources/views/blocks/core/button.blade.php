@php
	$text   = (string) ( $attributes['text'] ?? '' );
	$url    = (string) ( $attributes['url'] ?? '' );
	$target = (string) ( $attributes['linkTarget'] ?? '' );
	$rel    = (string) ( $attributes['rel'] ?? '' );
	$title  = (string) ( $attributes['title'] ?? '' );

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
