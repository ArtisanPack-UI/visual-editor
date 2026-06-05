@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;
	use ArtisanPackUI\VisualEditorRendererBlade\Support\UrlSanitizer;

	$content    = isset( $attributes['content'] ) && is_string( $attributes['content'] ) && '' !== $attributes['content']
		? $attributes['content']
		: __( 'Read more' );
	$linkTarget = isset( $attributes['linkTarget'] ) && '_blank' === $attributes['linkTarget'] ? '_blank' : '_self';
	$permalink  = UrlSanitizer::safe( isset( $attributes['_resolvedPermalink'] ) && is_string( $attributes['_resolvedPermalink'] ) ? $attributes['_resolvedPermalink'] : '' );
@endphp
@if ( '' === $permalink )
	{{-- No resolved permalink — degrade to a non-interactive <span> so we
		don't emit a focusable anchor with an empty `href` (keyboard /
		screen-reader trap). --}}
	<span{!! BlockSupports::wrapperAttrs( $attributes, [ 'wp-block-read-more' ] ) !!}>{{ $content }}</span>
@else
	<a href="{{ $permalink }}"@if ( '_blank' === $linkTarget ) target="_blank" rel="noopener noreferrer"@endif{!! BlockSupports::wrapperAttrs( $attributes, [ 'wp-block-read-more' ] ) !!}>{{ $content }}</a>
@endif
