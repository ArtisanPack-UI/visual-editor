@php
	use ArtisanPackUI\VisualEditorRendererBlade\Services\ResponsiveImages;
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

	// #1031 — variants, dimensions and a loading hint from the media record.
	$imageTag = '';

	if ( '' !== $mediaUrl && 'video' !== $mediaType ) {
		$responsive = app( ResponsiveImages::class )->forImage( $mediaUrl, $attributes['mediaId'] ?? null );
		$imageAttrs = sprintf( ' src="%s" alt="%s"', e( $mediaUrl ), e( $mediaAlt ) );

		if ( null !== $responsive->width && null !== $responsive->height ) {
			$imageAttrs .= sprintf( ' width="%d" height="%d"', $responsive->width, $responsive->height );
		}

		$imageTag = $responsive->wrap( '<img' . $imageAttrs . $responsive->attributes() . '/>' );
	}
@endphp
<div class="{{ implode( ' ', $classes ) }}"{!! $styleAttr !!}{!! $idAttr !!}>
	<figure class="wp-block-media-text__media">
		@if ( '' !== $mediaUrl )
			@if ( 'video' === $mediaType )
				<video controls src="{{ $mediaUrl }}"></video>
			@else
				{!! $imageTag !!}
			@endif
		@endif
	</figure>
	<div class="wp-block-media-text__content">
		{!! $innerBlocksHtml !!}
	</div>
</div>
