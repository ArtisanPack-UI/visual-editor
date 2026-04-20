@php
	$classes = [ 'wp-block-gallery', 'has-nested-images' ];

	if ( ! empty( $attributes['columns'] ) ) {
		$classes[] = 'columns-' . (int) $attributes['columns'];
	}

	if ( ! empty( $attributes['imageCrop'] ) ) {
		$classes[] = 'is-cropped';
	}

	if ( ! empty( $attributes['className'] ) ) {
		$classes[] = $attributes['className'];
	}

	$caption = (string) ( $attributes['caption'] ?? '' );
@endphp
<figure class="{{ implode( ' ', array_map( 'trim', $classes ) ) }}">
	{!! $innerBlocksHtml !!}
	@if ( '' !== trim( $caption ) )
		<figcaption class="blocks-gallery-caption">{!! $caption !!}</figcaption>
	@endif
</figure>
