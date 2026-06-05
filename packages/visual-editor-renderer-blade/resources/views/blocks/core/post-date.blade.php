@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;
	use ArtisanPackUI\VisualEditorRendererBlade\Support\UrlSanitizer;

	$displayType = isset( $attributes['displayType'] ) && 'modified' === $attributes['displayType'] ? 'modified' : 'date';
	$isLink      = ! empty( $attributes['isLink'] );

	$resolvedKey       = 'modified' === $displayType ? '_resolvedModifiedDate' : '_resolvedDate';
	$resolvedFormatKey = 'modified' === $displayType ? '_resolvedModifiedDateFormatted' : '_resolvedDateFormatted';

	$datetime  = isset( $attributes[ $resolvedKey ] ) && is_string( $attributes[ $resolvedKey ] ) ? $attributes[ $resolvedKey ] : '';
	$formatted = isset( $attributes[ $resolvedFormatKey ] ) && is_string( $attributes[ $resolvedFormatKey ] ) ? $attributes[ $resolvedFormatKey ] : $datetime;
	$permalink = UrlSanitizer::safe( isset( $attributes['_resolvedPermalink'] ) && is_string( $attributes['_resolvedPermalink'] ) ? $attributes['_resolvedPermalink'] : '' );

	$timeAttrs = '' !== $datetime ? sprintf( ' datetime="%s"', e( $datetime ) ) : '';
@endphp
<div{!! BlockSupports::wrapperAttrs( $attributes, [ 'wp-block-post-date' ] ) !!}>@if ( $isLink && '' !== $permalink )<a href="{{ $permalink }}"><time{!! $timeAttrs !!}>{{ $formatted }}</time></a>@else<time{!! $timeAttrs !!}>{{ $formatted }}</time>@endif</div>
