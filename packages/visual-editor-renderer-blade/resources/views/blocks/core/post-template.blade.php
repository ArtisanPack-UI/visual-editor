@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;

	$layout = isset( $attributes['layout'] ) ? (string) $attributes['layout'] : 'list';
	$columns = isset( $attributes['columns'] ) ? (int) $attributes['columns'] : 3;
	$isGrid = 'grid' === $layout;

	$classes = [ 'wp-block-post-template' ];
	$classes[] = $isGrid ? 'is-layout-grid' : 'is-layout-flow';
	if ( $isGrid ) {
		$classes[] = 'columns-' . $columns;
	}
@endphp
<ul{!! BlockSupports::wrapperAttrs( $attributes, $classes ) !!}>
	{!! $innerBlocksHtml !!}
</ul>
