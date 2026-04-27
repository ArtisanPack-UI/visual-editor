@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\UrlSanitizer;

	$align    = isset( $attributes['textAlign'] ) && is_string( $attributes['textAlign'] ) ? $attributes['textAlign'] : '';
	$moreText = isset( $attributes['moreText'] ) && is_string( $attributes['moreText'] ) ? $attributes['moreText'] : '';
	$showMoreOnNewLine = ! isset( $attributes['showMoreOnNewLine'] ) || ! empty( $attributes['showMoreOnNewLine'] );

	$excerpt   = isset( $attributes['_resolvedExcerpt'] ) && is_string( $attributes['_resolvedExcerpt'] ) ? $attributes['_resolvedExcerpt'] : '';
	$permalink = UrlSanitizer::safe( isset( $attributes['_resolvedPermalink'] ) && is_string( $attributes['_resolvedPermalink'] ) ? $attributes['_resolvedPermalink'] : '' );

	$classes = [ 'wp-block-post-excerpt' ];

	if ( '' !== $align ) {
		$classes[] = 'has-text-align-' . $align;
	}

	if ( ! empty( $attributes['className'] ) && is_string( $attributes['className'] ) ) {
		$classes[] = $attributes['className'];
	}

	$moreLink = '';

	if ( '' !== $moreText && '' !== $permalink ) {
		$moreLink = sprintf(
			'<a class="wp-block-post-excerpt__more-link" href="%s">%s</a>',
			e( $permalink ),
			e( $moreText )
		);
	} elseif ( '' !== $moreText ) {
		$moreLink = sprintf(
			'<span class="wp-block-post-excerpt__more-text">%s</span>',
			e( $moreText )
		);
	}
@endphp
<div class="{{ implode( ' ', array_map( 'trim', $classes ) ) }}">
	<p class="wp-block-post-excerpt__excerpt">{!! $excerpt !!}@if ( '' !== $moreLink && ! $showMoreOnNewLine ) {!! $moreLink !!}@endif</p>
	@if ( '' !== $moreLink && $showMoreOnNewLine )
		<p class="wp-block-post-excerpt__more-text">{!! $moreLink !!}</p>
	@endif
</div>
