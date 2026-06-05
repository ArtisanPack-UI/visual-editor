@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;

	$mediaUrl      = (string) ( $attributes['mediaUrl'] ?? '' );
	$mediaAlt      = (string) ( $attributes['mediaAlt'] ?? '' );
	$mediaType     = (string) ( $attributes['mediaType'] ?? 'image' );
	$mediaPosition = (string) ( $attributes['mediaPosition'] ?? 'left' );
	$mediaWidth    = isset( $attributes['mediaWidth'] ) ? (int) $attributes['mediaWidth'] : 50;

	$baseClasses = [ 'wp-block-media-text' ];

	if ( 'right' === $mediaPosition ) {
		$baseClasses[] = 'has-media-on-the-right';
	}

	if ( ! empty( $attributes['isStackedOnMobile'] ) ) {
		$baseClasses[] = 'is-stacked-on-mobile';
	}

	// Splice the grid-template-columns declaration into block-
	// supports' style output rather than emitting two `style="…"`
	// attributes.
	$compiled = BlockSupports::compile( $attributes );
	$classes  = array_values( array_unique( array_merge( $baseClasses, $compiled['classes'] ) ) );
	$style    = '' !== $compiled['style'] ? rtrim( $compiled['style'], ';' ) : '';

	if ( 50 !== $mediaWidth ) {
		$columns = 'left' === $mediaPosition
			? sprintf( '%d%% auto', $mediaWidth )
			: sprintf( 'auto %d%%', $mediaWidth );
		$style = ( '' !== $style ? $style . '; ' : '' ) . 'grid-template-columns: ' . $columns;
	}

	$styleAttr = '' !== $style ? sprintf( ' style="%s"', e( $style . ';' ) ) : '';
	$idAttr    = null !== $compiled['id'] ? sprintf( ' id="%s"', e( $compiled['id'] ) ) : '';
@endphp
<div class="{{ implode( ' ', $classes ) }}"{!! $styleAttr !!}{!! $idAttr !!}>
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
