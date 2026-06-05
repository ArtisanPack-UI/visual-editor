@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;

	$tagName = isset( $attributes['tagName'] ) && is_string( $attributes['tagName'] ) ? $attributes['tagName'] : 'div';
	$tagName = in_array( strtolower( $tagName ), [ 'div', 'section', 'article', 'aside', 'footer' ], true ) ? strtolower( $tagName ) : 'div';
@endphp
<{!! $tagName !!}{!! BlockSupports::wrapperAttrs( $attributes, [ 'wp-block-comments' ] ) !!}>
	{!! $innerBlocksHtml !!}
</{!! $tagName !!}>
