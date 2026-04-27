@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\UrlSanitizer;

	$align      = isset( $attributes['textAlign'] ) && is_string( $attributes['textAlign'] ) ? $attributes['textAlign'] : '';
	$showAvatar = ! isset( $attributes['showAvatar'] ) || ! empty( $attributes['showAvatar'] );
	$showBio    = ! empty( $attributes['showBio'] );
	$avatarSize = isset( $attributes['avatarSize'] ) ? max( 1, (int) $attributes['avatarSize'] ) : 24;
	$byline     = isset( $attributes['byline'] ) && is_string( $attributes['byline'] ) ? $attributes['byline'] : '';
	$isLink     = ! empty( $attributes['isLink'] );

	$name      = isset( $attributes['_resolvedAuthorName'] ) && is_string( $attributes['_resolvedAuthorName'] ) ? $attributes['_resolvedAuthorName'] : '';
	$bio       = isset( $attributes['_resolvedAuthorBio'] ) && is_string( $attributes['_resolvedAuthorBio'] ) ? $attributes['_resolvedAuthorBio'] : '';
	$authorUrl = UrlSanitizer::safe( isset( $attributes['_resolvedAuthorUrl'] ) && is_string( $attributes['_resolvedAuthorUrl'] ) ? $attributes['_resolvedAuthorUrl'] : '' );
	$avatarUrl = UrlSanitizer::safe( isset( $attributes['_resolvedAuthorAvatar'] ) && is_string( $attributes['_resolvedAuthorAvatar'] ) ? $attributes['_resolvedAuthorAvatar'] : '' );

	$classes = [ 'wp-block-post-author' ];

	if ( '' !== $align ) {
		$classes[] = 'has-text-align-' . $align;
	}

	if ( ! empty( $attributes['className'] ) && is_string( $attributes['className'] ) ) {
		$classes[] = $attributes['className'];
	}
@endphp
<div class="{{ implode( ' ', array_map( 'trim', $classes ) ) }}">
	@if ( $showAvatar && '' !== $avatarUrl )
		<div class="wp-block-post-author__avatar"><img alt="{{ $name }}" width="{{ $avatarSize }}" height="{{ $avatarSize }}" src="{{ $avatarUrl }}"/></div>
	@endif
	<div class="wp-block-post-author__content">
		@if ( '' !== $byline )
			<p class="wp-block-post-author__byline">{{ $byline }}</p>
		@endif
		@if ( $isLink && '' !== $authorUrl )
			<p class="wp-block-post-author__name"><a href="{{ $authorUrl }}">{{ $name }}</a></p>
		@else
			<p class="wp-block-post-author__name">{{ $name }}</p>
		@endif
		@if ( $showBio && '' !== $bio )
			<p class="wp-block-post-author__bio">{!! $bio !!}</p>
		@endif
	</div>
</div>
