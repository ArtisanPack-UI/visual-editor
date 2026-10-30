@php
	use ArtisanPackUI\VisualEditorRendererBlade\Services\ResponsiveImages;
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;
	use ArtisanPackUI\VisualEditorRendererBlade\Support\DuotoneSupport;
	use ArtisanPackUI\VisualEditorRendererBlade\Support\UrlSanitizer;

	$size       = isset( $attributes['size'] ) ? max( 1, (int) $attributes['size'] ) : 96;
	$isLink     = ! empty( $attributes['isLink'] );
	$linkTarget = isset( $attributes['linkTarget'] ) && is_string( $attributes['linkTarget'] ) ? $attributes['linkTarget'] : '_self';

	// #1031 — the smallest variant that's sharp at this size.
	$avatarUrl = app( ResponsiveImages::class )->avatarUrl(
		UrlSanitizer::safe( isset( $attributes['_resolvedAuthorAvatar'] ) && is_string( $attributes['_resolvedAuthorAvatar'] ) ? $attributes['_resolvedAuthorAvatar'] : '' ),
		$size,
	);
	$alt       = isset( $attributes['_resolvedAuthorName'] ) && is_string( $attributes['_resolvedAuthorName'] ) ? $attributes['_resolvedAuthorName'] : '';
	$authorUrl = UrlSanitizer::safe( isset( $attributes['_resolvedAuthorUrl'] ) && is_string( $attributes['_resolvedAuthorUrl'] ) ? $attributes['_resolvedAuthorUrl'] : '' );

	// F-2 — duotone filter on the avatar `<img>`, only when one renders.
	$duotoneClass = '' !== $avatarUrl ? DuotoneSupport::apply( $attributes, DuotoneSupport::TARGETS['core/avatar'] ) : '';

	// #952 — block.json skips border serialization: as in core, the
	// border (and so the radius) is painted on the `<img>`, which the
	// block-level wrapper wouldn't clip.
	$border       = BlockSupports::borderAttrs( $attributes );
	$imageClasses = implode( ' ', array_merge( [ 'avatar', 'wp-block-avatar__image' ], $border['classes'] ) );
	$imageAttrs   = sprintf( ' class="%s"', e( $imageClasses ) ) . ( '' !== $border['style'] ? sprintf( ' style="%s"', e( $border['style'] ) ) : '' );

	$wrapperAttributes = $attributes;
	unset( $wrapperAttributes['borderColor'], $wrapperAttributes['style']['border'] );

	$linkAttrs = 'href="' . e( $authorUrl ) . '"';
	if ( '_blank' === $linkTarget ) {
		$linkAttrs .= ' target="_blank" rel="noopener noreferrer"';
	}
@endphp
<div{!! BlockSupports::wrapperAttrs( $wrapperAttributes, [ 'wp-block-avatar', $duotoneClass ] ) !!}>
	@if ( '' !== $avatarUrl )
		@if ( $isLink && '' !== $authorUrl )
			<a {!! $linkAttrs !!}><img alt="{{ $alt }}" width="{{ $size }}" height="{{ $size }}" src="{{ $avatarUrl }}" loading="lazy"{!! $imageAttrs !!}/></a>
		@else
			<img alt="{{ $alt }}" width="{{ $size }}" height="{{ $size }}" src="{{ $avatarUrl }}" loading="lazy"{!! $imageAttrs !!}/>
		@endif
	@endif
</div>
