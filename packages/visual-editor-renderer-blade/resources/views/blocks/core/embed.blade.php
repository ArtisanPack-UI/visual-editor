@php
	$url      = (string) ( $attributes['url'] ?? '' );
	$provider = (string) ( $attributes['providerNameSlug'] ?? '' );
	$caption  = (string) ( $attributes['caption'] ?? '' );
	$ratio    = (string) ( $attributes['aspectRatio'] ?? '' );

	$classes = [ 'wp-block-embed' ];

	if ( '' !== $provider ) {
		$classes[] = 'is-provider-' . preg_replace( '/[^a-z0-9\-]/i', '-', $provider );
		$classes[] = 'wp-block-embed-' . preg_replace( '/[^a-z0-9\-]/i', '-', $provider );
	}

	if ( ! empty( $attributes['type'] ) ) {
		$classes[] = 'is-type-' . $attributes['type'];
	}

	if ( '' !== $ratio ) {
		$classes[] = 'wp-embed-aspect-' . str_replace( '/', '-', $ratio );
		$classes[] = 'wp-has-aspect-ratio';
	}
@endphp
<figure class="{{ implode( ' ', array_map( 'trim', $classes ) ) }}">
	<div class="wp-block-embed__wrapper">
		@if ( '' !== $url )
			{{ $url }}
		@endif
	</div>
	@if ( '' !== trim( $caption ) )
		<figcaption>{!! $caption !!}</figcaption>
	@endif
</figure>
