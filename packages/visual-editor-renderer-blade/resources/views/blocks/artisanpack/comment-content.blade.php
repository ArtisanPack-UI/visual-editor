@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;

	$content = isset( $attributes['_resolvedContent'] ) && is_string( $attributes['_resolvedContent'] ) ? $attributes['_resolvedContent'] : '';
	$align   = isset( $attributes['textAlign'] ) && is_string( $attributes['textAlign'] ) ? $attributes['textAlign'] : '';

	$classes = [ 'wp-block-comment-content' ];
	if ( '' !== $align ) {
		$classes[] = 'has-text-align-' . $align;
	}
@endphp
<div{!! BlockSupports::wrapperAttrs( $attributes, $classes ) !!}>
	{!! $content !!}
</div>
