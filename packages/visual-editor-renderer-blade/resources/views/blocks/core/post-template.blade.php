@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;

	$layout = isset( $attributes['layout'] ) ? (string) $attributes['layout'] : 'list';
	if ( ! in_array( $layout, [ 'list', 'grid', 'masonry' ], true ) ) {
		$layout = 'list';
	}
	$columns = isset( $attributes['columns'] ) ? (int) $attributes['columns'] : 3;
	$isGrid = 'grid' === $layout;
	$isMasonry = 'masonry' === $layout;
	$usesColumns = $isGrid || $isMasonry;

	// Masonry layers `is-layout-grid` underneath `is-layout-masonry` so
	// the existing post-template grid CSS (display: grid, columns-N
	// tracks) provides the baseline layout, and the masonry stylesheet
	// adds `grid-template-rows: masonry` on top via `@supports` for
	// browsers that ship native CSS Grid masonry. Non-supporting
	// browsers see the columned grid baseline until the JS bootstrap
	// hydrates and packs the items.
	$classes = [ 'wp-block-post-template' ];
	if ( $isGrid || $isMasonry ) {
		$classes[] = 'is-layout-grid';
	} else {
		$classes[] = 'is-layout-flow';
	}
	if ( $isMasonry ) {
		$classes[] = 'is-layout-masonry';
	}
	if ( $usesColumns ) {
		$classes[] = 'columns-' . $columns;
	}

	$attrs = BlockSupports::wrapperAttrs( $attributes, $classes );
	if ( $isMasonry ) {
		$attrs .= sprintf( ' data-ap-cols="%d"', $columns );
	}
@endphp
<ul{!! $attrs !!}>
	{!! $innerBlocksHtml !!}
</ul>
