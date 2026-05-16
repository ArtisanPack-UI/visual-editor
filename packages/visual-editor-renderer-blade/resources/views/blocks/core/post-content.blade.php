@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;

	$content = isset( $attributes['_resolvedContent'] ) && is_string( $attributes['_resolvedContent'] ) ? $attributes['_resolvedContent'] : '';
@endphp
<div{!! BlockSupports::wrapperAttrs( $attributes, [ 'entry-content', 'wp-block-post-content' ] ) !!}>{!! $content !!}</div>
