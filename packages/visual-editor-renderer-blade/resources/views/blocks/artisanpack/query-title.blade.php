@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;

	$level = isset( $attributes['level'] ) && is_numeric( $attributes['level'] ) ? (int) $attributes['level'] : 1;
	$tag   = 0 === $level ? 'p' : 'h' . $level;
	$tag   = in_array( $tag, [ 'p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ], true ) ? $tag : 'h1';

	$title = isset( $attributes['_resolvedQueryTitle'] ) && is_string( $attributes['_resolvedQueryTitle'] )
		? $attributes['_resolvedQueryTitle']
		: '';
@endphp
@if ( '' !== $title )
	<{{ $tag }}{!! BlockSupports::wrapperAttrs( $attributes, [ 'wp-block-query-title' ] ) !!}>{{ $title }}</{{ $tag }}>
@endif
