@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;
	use ArtisanPackUI\VisualEditorRendererBlade\Support\LayoutSupport;

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
	//
	// #700 — each shared modifier ships with its per-block compound; the
	// block-library alignment rules key on
	// `wp-block-post-template-is-layout-flow`. Masonry is an ArtisanPack
	// extension with no upstream compound, so it stays unpaired.
	$classes = array_merge(
		[ 'wp-block-post-template' ],
		LayoutSupport::pair( 'post-template', $usesColumns ? 'is-layout-grid' : 'is-layout-flow' )
	);
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
