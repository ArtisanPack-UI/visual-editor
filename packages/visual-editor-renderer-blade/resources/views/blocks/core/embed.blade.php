@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;

	$url      = (string) ( $attributes['url'] ?? '' );
	$provider = (string) ( $attributes['providerNameSlug'] ?? '' );
	$caption  = (string) ( $attributes['caption'] ?? '' );
	$ratio    = (string) ( $attributes['aspectRatio'] ?? '' );

	$baseClasses = [ 'wp-block-embed' ];

	if ( '' !== $provider ) {
		$baseClasses[] = 'is-provider-' . preg_replace( '/[^a-z0-9\-]/i', '-', $provider );
		$baseClasses[] = 'wp-block-embed-' . preg_replace( '/[^a-z0-9\-]/i', '-', $provider );
	}

	if ( ! empty( $attributes['type'] ) ) {
		$baseClasses[] = 'is-type-' . $attributes['type'];
	}

	if ( '' !== $ratio ) {
		$baseClasses[] = 'wp-embed-aspect-' . str_replace( '/', '-', $ratio );
		$baseClasses[] = 'wp-has-aspect-ratio';
	}
@endphp
<figure{!! BlockSupports::wrapperAttrs( $attributes, $baseClasses ) !!}>
	<div class="wp-block-embed__wrapper">
		@if ( '' !== $url )
			{{ $url }}
		@endif
	</div>
	@if ( '' !== trim( $caption ) )
		<figcaption>{!! $caption !!}</figcaption>
	@endif
</figure>
