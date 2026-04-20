@php
	$mediaUrl      = (string) ( $attributes['mediaUrl'] ?? '' );
	$mediaAlt      = (string) ( $attributes['mediaAlt'] ?? '' );
	$mediaType     = (string) ( $attributes['mediaType'] ?? 'image' );
	$mediaPosition = (string) ( $attributes['mediaPosition'] ?? 'left' );
	$mediaWidth    = isset( $attributes['mediaWidth'] ) ? (int) $attributes['mediaWidth'] : 50;

	$classes = [ 'wp-block-media-text' ];

	if ( 'right' === $mediaPosition ) {
		$classes[] = 'has-media-on-the-right';
	}

	if ( ! empty( $attributes['isStackedOnMobile'] ) ) {
		$classes[] = 'is-stacked-on-mobile';
	}

	if ( ! empty( $attributes['className'] ) ) {
		$classes[] = $attributes['className'];
	}

	$styleAttr = '';

	if ( 50 !== $mediaWidth ) {
		$columns = 'left' === $mediaPosition
			? sprintf( '%d%% auto', $mediaWidth )
			: sprintf( 'auto %d%%', 100 - $mediaWidth );

		$styleAttr = sprintf( ' style="grid-template-columns: %s;"', e( $columns ) );
	}
@endphp
<div class="{{ implode( ' ', array_map( 'trim', $classes ) ) }}"{!! $styleAttr !!}>
	<figure class="wp-block-media-text__media">
		@if ( '' !== $mediaUrl )
			@if ( 'video' === $mediaType )
				<video controls src="{{ $mediaUrl }}"></video>
			@else
				<img src="{{ $mediaUrl }}" alt="{{ $mediaAlt }}"/>
			@endif
		@endif
	</figure>
	<div class="wp-block-media-text__content">
		{!! $innerBlocksHtml !!}
	</div>
</div>
