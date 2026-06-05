@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;
	use ArtisanPackUI\VisualEditorRendererBlade\Support\UrlSanitizer;

	$moreText = isset( $attributes['moreText'] ) && is_string( $attributes['moreText'] ) ? $attributes['moreText'] : '';
	$showMoreOnNewLine = ! isset( $attributes['showMoreOnNewLine'] ) || ! empty( $attributes['showMoreOnNewLine'] );

	$excerpt   = isset( $attributes['_resolvedExcerpt'] ) && is_string( $attributes['_resolvedExcerpt'] ) ? $attributes['_resolvedExcerpt'] : '';
	$permalink = UrlSanitizer::safe( isset( $attributes['_resolvedPermalink'] ) && is_string( $attributes['_resolvedPermalink'] ) ? $attributes['_resolvedPermalink'] : '' );

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
<div{!! BlockSupports::wrapperAttrs( $attributes, [ 'wp-block-post-excerpt' ] ) !!}>
	<p class="wp-block-post-excerpt__excerpt">{!! $excerpt !!}@if ( '' !== $moreLink && ! $showMoreOnNewLine ) {!! $moreLink !!}@endif</p>
	@if ( '' !== $moreLink && $showMoreOnNewLine )
		<p class="wp-block-post-excerpt__more-text">{!! $moreLink !!}</p>
	@endif
</div>
