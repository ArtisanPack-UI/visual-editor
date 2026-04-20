@php
	$classes = [ 'wp-block-pullquote' ];

	if ( ! empty( $attributes['className'] ) ) {
		$classes[] = $attributes['className'];
	}

	$value    = (string) ( $attributes['value'] ?? '' );
	$citation = (string) ( $attributes['citation'] ?? '' );
@endphp
<figure class="{{ implode( ' ', array_map( 'trim', $classes ) ) }}">
	<blockquote>
		@if ( '' !== trim( $value ) )
			{!! $value !!}
		@endif
		@if ( '' !== trim( $citation ) )
			<cite>{!! $citation !!}</cite>
		@endif
	</blockquote>
</figure>
