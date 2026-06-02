@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;

	$count = isset( $attributes['_resolvedCommentCount'] ) && is_numeric( $attributes['_resolvedCommentCount'] ) ? (int) $attributes['_resolvedCommentCount'] : 0;
@endphp
<div{!! BlockSupports::wrapperAttrs( $attributes, [ 'wp-block-post-comments-count' ] ) !!}>{{ $count }}</div>
