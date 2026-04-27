@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\UrlSanitizer;

	$align       = isset( $attributes['textAlign'] ) && is_string( $attributes['textAlign'] ) ? $attributes['textAlign'] : '';
	$displayType = isset( $attributes['displayType'] ) && 'modified' === $attributes['displayType'] ? 'modified' : 'date';
	$isLink      = ! empty( $attributes['isLink'] );

	$resolvedKey       = 'modified' === $displayType ? '_resolvedModifiedDate' : '_resolvedDate';
	$resolvedFormatKey = 'modified' === $displayType ? '_resolvedModifiedDateFormatted' : '_resolvedDateFormatted';

	$datetime  = isset( $attributes[ $resolvedKey ] ) && is_string( $attributes[ $resolvedKey ] ) ? $attributes[ $resolvedKey ] : '';
	$formatted = isset( $attributes[ $resolvedFormatKey ] ) && is_string( $attributes[ $resolvedFormatKey ] ) ? $attributes[ $resolvedFormatKey ] : $datetime;
	$permalink = UrlSanitizer::safe( isset( $attributes['_resolvedPermalink'] ) && is_string( $attributes['_resolvedPermalink'] ) ? $attributes['_resolvedPermalink'] : '' );

	$classes = [ 'wp-block-post-date' ];

	if ( '' !== $align ) {
		$classes[] = 'has-text-align-' . $align;
	}

	if ( ! empty( $attributes['className'] ) && is_string( $attributes['className'] ) ) {
		$classes[] = $attributes['className'];
	}

	$timeAttrs = '' !== $datetime ? sprintf( ' datetime="%s"', e( $datetime ) ) : '';
@endphp
<div class="{{ implode( ' ', array_map( 'trim', $classes ) ) }}">@if ( $isLink && '' !== $permalink )<a href="{{ $permalink }}"><time{!! $timeAttrs !!}>{{ $formatted }}</time></a>@else<time{!! $timeAttrs !!}>{{ $formatted }}</time>@endif</div>
