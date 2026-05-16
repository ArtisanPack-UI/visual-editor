@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;
	use ArtisanPackUI\VisualEditorRendererBlade\Support\UrlSanitizer;

	$rawLevel = isset( $attributes['level'] ) ? (int) $attributes['level'] : 2;
	$level    = max( 1, min( 6, $rawLevel ) );
	$tag      = 'h' . $level;
	$isLink   = ! empty( $attributes['isLink'] );
	$linkTarget = isset( $attributes['linkTarget'] ) && is_string( $attributes['linkTarget'] ) ? $attributes['linkTarget'] : '';
	$rel        = isset( $attributes['rel'] ) && is_string( $attributes['rel'] ) ? $attributes['rel'] : '';
	$linkClass  = isset( $attributes['linkClass'] ) && is_string( $attributes['linkClass'] ) ? $attributes['linkClass'] : '';

	$title     = isset( $attributes['_resolvedTitle'] ) && is_string( $attributes['_resolvedTitle'] ) ? $attributes['_resolvedTitle'] : '';
	$permalink = UrlSanitizer::safe( isset( $attributes['_resolvedPermalink'] ) && is_string( $attributes['_resolvedPermalink'] ) ? $attributes['_resolvedPermalink'] : '' );

	$linkAttrs = '';

	if ( $isLink && '' !== $permalink ) {
		$linkAttrs .= sprintf( ' href="%s"', e( $permalink ) );

		if ( '' !== $linkClass ) {
			$linkAttrs .= sprintf( ' class="%s"', e( $linkClass ) );
		}

		if ( '_blank' === $linkTarget ) {
			$linkAttrs .= sprintf( ' target="_blank" rel="%s"', e( trim( 'noopener noreferrer ' . $rel ) ) );
		} elseif ( '' !== $rel ) {
			$linkAttrs .= sprintf( ' rel="%s"', e( $rel ) );
		}
	}
@endphp
<{{ $tag }}{!! BlockSupports::wrapperAttrs( $attributes, [ 'wp-block-post-title' ] ) !!}>@if ( $isLink && '' !== $permalink )<a{!! $linkAttrs !!}>{!! $title !!}</a>@else{!! $title !!}@endif</{{ $tag }}>
