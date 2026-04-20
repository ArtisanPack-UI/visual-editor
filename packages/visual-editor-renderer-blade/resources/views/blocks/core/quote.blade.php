@php
	$classes = [ 'wp-block-quote' ];

	if ( ! empty( $attributes['className'] ) ) {
		$classes[] = $attributes['className'];
	}

	$citation = (string) ( $attributes['citation'] ?? '' );
@endphp
<blockquote class="{{ implode( ' ', array_map( 'trim', $classes ) ) }}">
	{!! $innerBlocksHtml !!}
	@if ( '' !== trim( $citation ) )
		<cite>{!! $citation !!}</cite>
	@endif
</blockquote>
