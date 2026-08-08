@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;
	use ArtisanPackUI\VisualEditorRendererBlade\Support\LayoutSupport;

	// Three saved shapes describe the same layout, mirroring the tolerance
	// `QueryInliner::postTemplateLayoutIsGrid()` already applies when it
	// stamps `_resolvedGridSpan` onto each item: the ArtisanPack
	// post-template's plain `layout` string, an upstream
	// `core/post-template` mirror's object-form `layout.type`, and
	// Gutenberg's generic block-supports sibling `layoutType`. Reading only
	// the first meant a tree the inliner treated as a grid rendered its
	// wrapper as flow, so the stamped span classes had no grid to lay out.
	$layoutAttr = $attributes['layout'] ?? null;

	if ( is_string( $layoutAttr ) ) {
		$layout = $layoutAttr;
	} elseif ( is_array( $layoutAttr ) && isset( $layoutAttr['type'] ) && is_string( $layoutAttr['type'] ) ) {
		$layout = $layoutAttr['type'];
	} else {
		$layout = '';
	}

	if ( ! in_array( $layout, [ 'list', 'grid', 'masonry' ], true ) ) {
		$layout = 'list';
	}

	if ( 'list' === $layout && isset( $attributes['layoutType'] ) && 'grid' === $attributes['layoutType'] ) {
		$layout = 'grid';
	}

	// Mirrors the JS renderers' `clampColumns()`: numeric strings survive
	// serializers that drop number types, anything unparseable falls back
	// to the default, and the result is clamped to the bounds the
	// stylesheet actually has `columns-N` rules for. Without the clamp,
	// Blade alone could emit `columns-0` or `columns-99`.
	$rawColumns = $attributes['columns'] ?? null;

	if ( is_int( $rawColumns ) || is_float( $rawColumns ) ) {
		$columns = (int) $rawColumns;
	} elseif ( is_string( $rawColumns ) && 1 === preg_match( '/^\s*[+-]?\d+/', $rawColumns ) ) {
		$columns = (int) $rawColumns;
	} else {
		$columns = 3;
	}

	$columns = max( 1, min( 12, $columns ) );

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
